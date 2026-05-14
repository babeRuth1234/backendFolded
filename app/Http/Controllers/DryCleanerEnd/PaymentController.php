<?php

namespace App\Http\Controllers\DryCleanerEnd;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\User;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PaymentController extends Controller
{
    public function __construct(protected PaystackService $paystack) {}

    /**
     * Generate a Paystack payment link and return a QR code URL.
     * Called when DryCleaner clicks "Receive Payment".
     */
    public function initiate(Request $request, string $jobId)
    {
        $job  = Job::with('user')->findOrFail($jobId);
        $user = $job->user;

        if ($job->payment_status === 'paid') {
            return response()->json(['message' => 'Job already paid.'], 422);
        }

        $reference = 'FOLDED-' . strtoupper(Str::random(10));

        // Use email if available, otherwise use a placeholder
        $email = $user->email ?? "{$user->phone}@folded.app";

        $data = $this->paystack->initializeTransaction(
            email: $email,
            amountNaira: $job->total_price,
            reference: $reference,
            metadata: [
                'job_id'   => (string) $job->_id,
                'order_ref' => $job->order_ref,
                'client'   => $user->name,
            ]
        );

        // Save reference to job for callback verification
        $job->update(['paystack_reference' => $reference]);

        // Return the payment URL — frontend converts this to QR code display
        return response()->json([
            'payment_url' => $data['authorization_url'],
            'reference'   => $reference,
        ]);
    }

    /**
     * Paystack webhook / redirect callback to verify and complete payment.
     */
    public function callback(Request $request)
    {
        $reference = $request->query('reference') ?? $request->reference;

        if (! $reference) {
            return response()->json(['message' => 'No reference provided.'], 422);
        }

        $data = $this->paystack->verifyTransaction($reference);

        if ($data['status'] !== 'success') {
            return response()->json(['message' => 'Payment not successful.', 'status' => $data['status']], 402);
        }

        $jobId = $data['metadata']['job_id'] ?? null;
        $job   = $jobId ? Job::find($jobId) : Job::where('paystack_reference', $reference)->first();

        if (! $job) {
            return response()->json(['message' => 'Job not found.'], 404);
        }

        $job->update([
            'payment_status' => 'paid',
            'status'         => 'completed',
        ]);

        \App\Events\JobUpdated::dispatch([
            'id' => (string) $job->_id,
            'user_id' => (string) $job->user_id,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);

        // Redirect client to their dashboard on successful payment
        return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/client/dashboard?payment=success');
    }
}
