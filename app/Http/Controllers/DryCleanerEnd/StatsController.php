<?php

namespace App\Http\Controllers\DryCleanerEnd;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\User;
use App\Models\Category;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    /**
     * Business insights for the DryCleaner stats page.
     */
    public function index(Request $request)
    {
        $period = $request->get('period', '7'); // days

        $from = now()->subDays((int) $period)->startOfDay();

        // -- Totals --
        $totalJobs       = Job::count();
        $activeCustomers = User::where('role', 'client')->count();
        $revenueThisWeek = Job::where('payment_status', 'paid')
            ->where('updated_at', '>=', now()->startOfWeek())
            ->sum('total_price');

        // -- Job status breakdown --
        $inProgress = Job::where('status', 'in_progress')->count();
        $completed  = Job::where('status', 'completed')->count();
        $ready      = Job::where('status', 'ready')->count();
        $overdue    = Job::whereNotIn('status', ['completed'])
            ->where('return_date', '<', now())
            ->count();

        // -- Weekly earnings (last 7 days by day) --
        $weeklyEarnings = [];
        for ($i = 6; $i >= 0; $i--) {
            $day   = now()->subDays($i);
            $label = $day->format('D'); // Mon, Tue...
            $total = Job::where('payment_status', 'paid')
                ->whereBetween('updated_at', [$day->startOfDay()->toDateTimeString(), $day->copy()->endOfDay()->toDateTimeString()])
                ->sum('total_price');
            $weeklyEarnings[] = ['day' => $label, 'revenue' => round($total, 2)];
        }

        // -- Popular categories (based on items in all jobs) --
        $jobs       = Job::whereNotNull('items')->get();
        $catTotals  = [];
        foreach ($jobs as $job) {
            foreach (($job->items ?? []) as $item) {
                $name = $item['name'] ?? 'Unknown';
                $catTotals[$name] = ($catTotals[$name] ?? 0) + ($item['quantity'] ?? 0);
            }
        }
        $totalItems = array_sum($catTotals);
        arsort($catTotals);
        $popularServices = collect($catTotals)->take(5)->map(fn($qty, $name) => [
            'name'       => $name,
            'quantity'   => $qty,
            'percentage' => $totalItems > 0 ? round(($qty / $totalItems) * 100) : 0,
        ])->values();

        return response()->json([
            'total_jobs'       => $totalJobs,
            'active_customers' => $activeCustomers,
            'revenue_this_week' => round($revenueThisWeek, 2),
            'job_status' => [
                'in_progress' => $inProgress,
                'ready'       => $ready,
                'completed'   => $completed,
                'overdue'     => $overdue,
            ],
            'weekly_earnings'  => $weeklyEarnings,
            'popular_services' => $popularServices,
        ]);
    }
}
