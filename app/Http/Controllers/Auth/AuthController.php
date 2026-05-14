<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(protected NotificationService $notifier) {}

    // --------------------------------------------------------
    // LOGIN (both roles)
    // --------------------------------------------------------
    public function login(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string', // email or phone
            'password'   => 'required|string',
        ]);

        $identifier = $request->identifier;
        $user = User::where('email', $identifier)
                    ->orWhere('phone', $identifier)
                    ->first();

        // If user has no password set yet (auto-created account), force setup
        if ($user && ! $user->password) {
            return response()->json([
                'requires_password_setup' => true,
                'user_id' => (string) $user->_id,
            ], 403);
        }

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => ['The provided credentials are incorrect.'],
            ]);
        }


        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => (string) $user->_id,
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role'  => $user->role,
            ],
        ]);
    }

    // --------------------------------------------------------
    // FIRST-TIME PASSWORD SETUP (client accounts auto-created)
    // --------------------------------------------------------
    public function setupPassword(Request $request)
    {
        $request->validate([
            'user_id'  => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::findOrFail($request->user_id);

        if ($user->password) {
            return response()->json(['message' => 'Password already set.'], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => (string) $user->_id,
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role'  => $user->role,
            ],
        ]);
    }

    // --------------------------------------------------------
    // LOGOUT
    // --------------------------------------------------------
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    // --------------------------------------------------------
    // GET CURRENT USER
    // --------------------------------------------------------
    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id'    => (string) $user->_id,
            'name'  => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role'  => $user->role,
        ]);
    }

    // --------------------------------------------------------
    // DELETE ACCOUNT
    // --------------------------------------------------------
    public function destroy(Request $request)
    {
        $user = $request->user();
        
        if ($user->role !== 'client') {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        // Delete all jobs associated with this client
        \App\Models\Job::where('user_id', (string) $user->_id)->delete();

        // Delete tokens and user
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Account and all associated jobs deleted successfully.']);
    }
}
