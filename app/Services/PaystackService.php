<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class PaystackService
{
    protected string $secretKey;
    protected string $publicKey;
    protected string $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret');
        $this->publicKey = config('services.paystack.key');
    }

    /**
     * Initialize a Paystack transaction and return the authorization URL.
     */
    public function initializeTransaction(string $email, float $amountNaira, string $reference, array $metadata = []): array
    {
        $amountKobo = (int) round($amountNaira * 100);

        $response = Http::withoutVerifying()->withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", [
                'email'     => $email,
                'amount'    => $amountKobo,
                'reference' => $reference,
                'callback_url' => env('APP_URL') . '/api/client/payment/callback',
                'metadata'  => $metadata,
            ]);

        if (! $response->successful() || ! $response->json('status')) {
            throw new Exception('Paystack init failed: ' . $response->body());
        }

        return $response->json('data');
    }

    /**
     * Verify a Paystack transaction by reference.
     */
    public function verifyTransaction(string $reference): array
    {
        $response = Http::withoutVerifying()->withToken($this->secretKey)
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        if (! $response->successful() || ! $response->json('status')) {
            throw new Exception('Paystack verify failed: ' . $response->body());
        }

        return $response->json('data');
    }
}
