<?php

namespace App\Http\Controllers\DryCleanerEnd;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\User;
use App\Models\Setting;
use App\Events\JobUpdated;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class JobController extends Controller
{
    public function __construct(protected NotificationService $notifier) {}

    /**
     * List all jobs with optional filters.
     * Supports: filter=urgency|price|quantity, search=string, status=string
     */
    public function index(Request $request)
    {
        $query = Job::with('user');

        // Text search on client name or job ref
        if ($search = $request->search) {
            $userIds = User::where('name', 'regexp', "/.*{$search}.*/i")->pluck('_id')->map(fn($id) => (string) $id)->toArray();
            $query->where(function ($q) use ($search, $userIds) {
                $q->where('order_ref', 'regexp', "/.*{$search}.*/i")
                  ->orWhereIn('user_id', $userIds);
            });
        }

        // Status filter
        if ($status = $request->status) {
            $query->where('status', $status);
        }

        $jobs = $query->get()->map(fn($job) => $this->formatJob($job));

        // Strict Queue Sorting: Active jobs first, ordered chronologically (oldest first). Completed jobs last.
        $jobs = $jobs->sortBy(function($job) {
            if ($job['status'] === 'completed') return 'Z_' . $job['created_at'];
            return 'A_' . $job['created_at'];
        })->values();

        $active   = Job::whereNotIn('status', ['completed'])->count();
        $dueToday = Job::whereNotIn('status', ['completed'])
            ->whereBetween('return_date', [now()->startOfDay(), now()->endOfDay()])
            ->count();

        return response()->json([
            'active_jobs' => $active,
            'due_today'   => $dueToday,
            'jobs'        => $jobs,
        ]);
    }

    /**
     * Create a new job (DryCleaner intake flow).
     * Expects: user_id (or new_customer data), items array, return_date, pickup_window
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id'       => 'nullable|string',
            'items'         => 'required|array|min:1',
            'items.*.category_id'    => 'required|string',
            'items.*.name'           => 'required|string',
            'items.*.price_per_unit' => 'required|numeric',
            'items.*.quantity'       => 'required|integer|min:1',
            'return_date'   => 'nullable|date',
            'pickup_window' => 'nullable|string',
        ]);

        // Resolve user
        $user = User::findOrFail($request->user_id);

        // Calculate pricing
        $subtotal = 0;
        $items = collect($request->items)->map(function ($item) use (&$subtotal) {
            $total = $item['price_per_unit'] * $item['quantity'];
            $subtotal += $total;
            return array_merge($item, ['total' => $total]);
        })->toArray();

        // Apply new customer discount if applicable
        $discountPct     = 0;
        $discountApplied = 0;
        if ($user->is_new_customer) {
            $discountPct     = (float) (Setting::where('key', 'new_customer_discount')->value('value') ?? 10);
            $discountApplied = round($subtotal * ($discountPct / 100), 2);
        }

        $totalPrice  = round($subtotal - $discountApplied, 2);
        $totalItems  = array_sum(array_column($items, 'quantity'));
        $orderRef    = 'LF-' . strtoupper(Str::random(5));

        $job = Job::create([
            'user_id'          => (string) $user->_id,
            'order_ref'        => $orderRef,
            'status'           => 'intake',
            'items'            => $items,
            'subtotal'         => $subtotal,
            'discount_applied' => $discountApplied,
            'total_price'      => $totalPrice,
            'total_items'      => $totalItems,
            'return_date'      => $request->return_date,
            'pickup_window'    => $request->pickup_window,
            'payment_status'   => 'pending',
        ]);

        // Mark user no longer new
        $user->update(['is_new_customer' => false]);

        // Notify client
        $this->notifier->notifyJobCreated($user, $job);

        // Try to auto-advance queue
        $this->autoAdvanceQueue();

        $formattedJob = $this->formatJob($job->load('user'));
        JobUpdated::dispatch($formattedJob);

        return response()->json($formattedJob, 201);
    }

    /**
     * Mark job as "ready" (dry cleaning done), notify client.
     */
    public function markReady(string $id)
    {
        $job = Job::findOrFail($id);
        $job->update(['status' => 'ready']);

        $user = User::findOrFail($job->user_id);
        $this->notifier->notifyJobReady($user, $job);

        // Auto-advance the next job in the queue
        $this->autoAdvanceQueue();

        $formattedJob = $this->formatJob($job->load('user'));
        JobUpdated::dispatch($formattedJob);

        return response()->json(['message' => 'Job marked as ready. Client notified.', 'job' => $formattedJob]);
    }

    /**
     * Show a single job.
     */
    public function show(string $id)
    {
        $job = Job::with('user')->findOrFail($id);
        return response()->json($this->formatJob($job));
    }

    /**
     * Auto-advance the queue: If no job is in_progress, take the oldest intake job and make it in_progress.
     */
    private function autoAdvanceQueue()
    {
        $hasInProgress = Job::where('status', 'in_progress')->exists();
        if (!$hasInProgress) {
            $nextJob = Job::where('status', 'intake')->orderBy('created_at', 'asc')->first();
            if ($nextJob) {
                $nextJob->update(['status' => 'in_progress']);
                $formattedJob = $this->formatJob($nextJob->load('user'));
                JobUpdated::dispatch($formattedJob);
            }
        }
    }

    // ------------------------------------------------
    // Internal formatter
    // ------------------------------------------------
    private function formatJob(Job $job): array
    {
        return [
            'id'               => (string) $job->_id,
            'order_ref'        => $job->order_ref,
            'status'           => $job->status,
            'payment_status'   => $job->payment_status,
            'items'            => $job->items,
            'total_items'      => $job->total_items,
            'subtotal'         => $job->subtotal,
            'discount_applied' => $job->discount_applied,
            'total_price'      => $job->total_price,
            'return_date'      => $job->return_date,
            'pickup_window'    => $job->pickup_window,

            'client'           => $job->user ? [
                'id'    => (string) $job->user->_id,
                'name'  => $job->user->name,
                'email' => $job->user->email,
                'phone' => $job->user->phone,
            ] : null,
            'created_at'       => $job->created_at,
        ];
    }
}
