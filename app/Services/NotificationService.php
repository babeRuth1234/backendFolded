<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class NotificationService
{
    /**
     * Send an SMS. In production this will use Twilio.
     * For now it writes to storage/logs/sms.txt
     */
    public function sendSms(string $to, string $message): bool
    {
        // Aggressively clean the phone number: remove spaces, dashes, parentheses, and +
        $to = preg_replace('/[\s\-\(\)\+]+/', '', $to);

        // Format Nigerian numbers properly for Termii (e.g. 090... -> 23490...)
        if (str_starts_with($to, '0') && strlen($to) === 11) {
            $to = '234' . substr($to, 1);
        }

        try {
            $response = Http::withOptions(['verify' => false])->post('https://api.ng.termii.com/api/sms/send', [
                'to' => $to,
                'from' => env('TERMII_SENDER_ID', 'N-Alert'),
                'sms' => $message,
                'type' => 'plain',
                'channel' => 'generic',
                'api_key' => env('TERMII_API_KEY'),
            ]);

            if ($response->successful()) {
                Log::info("Termii SMS Sent [{$to}]: {$message}");
                return true;
            } else {
                Log::error("Termii SMS Failed: " . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Termii SMS Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send an Email (uses Laravel's built-in Mailable / log driver).
     */
    public function sendEmail(string $to, string $subject, string $body): bool
    {
        \Illuminate\Support\Facades\Mail::raw($body, function ($msg) use ($to, $subject) {
            $msg->to($to)->subject($subject);
        });
        return true;
    }

    /**
     * Notify a user (client) about a new job intake.
     * Sends via SMS if phone is set, via email if email is set.
     */
    public function notifyJobCreated(\App\Models\User $client, \App\Models\Job $job): void
    {
        $orderRef = strtoupper(substr((string) $job->_id, -6));
        $total    = number_format($job->total_price, 2);
        $url      = env('APP_URL') . '/client/dashboard';
        $message  = "Welcome to Folded! Your laundry order #{$orderRef} has been received. "
                  . "Total: \${$total}. Track your laundry at: {$url}";

        if ($client->phone) {
            $this->sendSms($client->phone, $message);
        }
        if ($client->email) {
            $this->sendEmail($client->email, 'Your Laundry Order Has Been Received', $message);
        }
    }

    /**
     * Notify a user (client) that their laundry is ready for pickup.
     */
    public function notifyJobReady(\App\Models\User $client, \App\Models\Job $job): void
    {
        $orderRef = strtoupper(substr((string) $job->_id, -6));
        $url      = env('APP_URL') . '/client/dashboard';
        $message  = "Great news! Your laundry order #{$orderRef} is clean and ready for pickup. "
                  . "Visit: {$url} to confirm.";

        if ($client->phone) {
            $this->sendSms($client->phone, $message);
        }
        if ($client->email) {
            $this->sendEmail($client->email, 'Your Laundry is Ready!', $message);
        }
    }
}
