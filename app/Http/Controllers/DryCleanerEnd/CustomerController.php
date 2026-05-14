<?php

namespace App\Http\Controllers\DryCleanerEnd;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function __construct(protected NotificationService $notifier) {}

    /**
     * Search existing clients by name or email.
     */
    public function search(Request $request)
    {
        $request->validate(['q' => 'required|string|min:1']);
        $q = $request->q;

        $clients = User::where('role', 'client')
            ->where(function ($query) use ($q) {
                $query->where('name', 'regexp', "/.*{$q}.*/i")
                      ->orWhere('email', 'regexp', "/.*{$q}.*/i")
                      ->orWhere('phone', 'regexp', "/.*{$q}.*/i");
            })
            ->limit(10)
            ->get()
            ->map(fn($u) => [
                'id'         => (string) $u->_id,
                'name'       => $u->name,
                'email'      => $u->email,
                'phone'      => $u->phone,
                'last_visit' => $u->updated_at,
            ]);

        return response()->json($clients);
    }

    /**
     * Create a new client account via phone or email.
     * A temporary password is NOT set — client must set password on first login.
     */
    public function createNewCustomer(Request $request)
    {
        $request->validate([
            'name'  => 'nullable|string|max:120',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        if (!$request->phone && !$request->email) {
            return response()->json(['message' => 'Phone or email required.'], 422);
        }

        // Check if client already exists
        $existingQuery = User::where('role', 'client');
        if ($request->phone) {
            $existingQuery->where('phone', $request->phone);
        } elseif ($request->email) {
            $existingQuery->where('email', $request->email);
        }

        if ($existing = $existingQuery->first()) {
            return response()->json([
                'user'    => [
                    'id'    => (string) $existing->_id,
                    'name'  => $existing->name,
                    'email' => $existing->email,
                    'phone' => $existing->phone,
                ],
                'already_exists' => true,
                'is_new_customer' => $existing->is_new_customer,
            ]);
        }

        // Auto-generate name if not provided
        $name = $request->name ?? ($request->phone ?? $request->email);

        $client = User::create([
            'name'            => $name,
            'phone'           => $request->phone,
            'email'           => $request->email,
            'role'            => 'client',
            'password'        => null, // client sets password on first login
            'is_new_customer' => true,
        ]);

        return response()->json([
            'user' => [
                'id'    => (string) $client->_id,
                'name'  => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
            ],
            'already_exists'  => false,
            'is_new_customer' => true,
        ], 201);
    }
}
