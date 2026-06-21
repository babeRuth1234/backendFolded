<?php

namespace App\Http\Controllers\ClientEnd;

use App\Http\Controllers\Controller;
use App\Models\Job;
use Illuminate\Http\Request;

class JobController extends Controller
{
    /**
     * List the authenticated client's jobs.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $jobs = Job::where('user_id', (string) $user->_id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($job) => $this->formatJob($job));

        return response()->json($jobs);
    }

    /**
     * Get a single job belonging to the authenticated client.
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $job  = Job::where('user_id', (string) $user->_id)->findOrFail($id);
        return response()->json($this->formatJob($job));
    }

    /**
     * Get the public queue of all active jobs.
     */
    public function queue(Request $request)
    {
        $jobs = Job::whereNotIn('status', ['completed'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($job) {
                return [
                    'id' => (string) $job->_id,
                    'user_id' => $job->user_id,
                    'status' => $job->status,
                    'created_at' => $job->created_at,
                ];
            });

        return response()->json($jobs);
    }

    private function formatJob(Job $job): array
    {
        return [
            'id'               => (string) $job->_id,
            'order_ref'        => $job->order_ref,
            'status'           => $job->status,
            'payment_status'   => $job->payment_status,
            'items'            => $job->items,
            'total_items'      => $job->total_items,
            'total_price'      => $job->total_price,
            'discount_applied' => $job->discount_applied,
            'return_date'      => $job->return_date,
            'pickup_window'    => $job->pickup_window,

            'created_at'       => $job->created_at,
        ];
    }
}
