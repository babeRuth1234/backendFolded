<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

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

        // Format Nigerian numbers properly for Twilio (e.g. 090... -> +23490...)
        if (str_starts_with($to, '0') && strlen($to) === 11) {
            $to = '+234' . substr($to, 1);
        } elseif (!str_starts_with($to, '+')) {
            // Just in case they typed 23490... without the plus
            $to = '+' . ltrim($to, '+');
        }

        try {
            $httpClient = new \Twilio\Http\CurlClient([CURLOPT_SSL_VERIFYPEER => false]);
            $twilio = new \Twilio\Rest\Client(env('TWILIO_SID'), env('TWILIO_TOKEN'), null, null, $httpClient);
            $twilio->messages->create('whatsapp:' . $to, [
                'from' => 'whatsapp:' . env('TWILIO_FROM'),
                'body' => $message,
            ]);
            
            Log::info("Twilio WhatsApp Sent [{$to}]: {$message}");
            return true;
        } catch (\Exception $e) {
            Log::error("Twilio WhatsApp Failed: " . $e->getMessage());
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
        $url      = env('FRONTEND_URL') . '/client/dashboard';
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
        $url      = env('FRONTEND_URL') . '/client/dashboard';
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
