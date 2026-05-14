<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $jobs = App\Models\Job::where('user_id', '6a05049757f4c897bb0e65d5')->orderBy('created_at', 'desc')->get();
    foreach($jobs as $job) {
        $data = [
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
    echo "SUCCESS\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
