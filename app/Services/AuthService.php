<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Authenticate a user by email and password, then issue a Sanctum token.
     *
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        Log::info('[AuthService@login] Login attempt', ['email' => $credentials['email']]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            Log::warning('[AuthService@login] Invalid credentials', ['email' => $credentials['email']]);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            Log::warning('[AuthService@login] Inactive user attempted login', ['user_id' => $user->id]);

            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        Log::info('[AuthService@login] Login successful', ['user_id' => $user->id]);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
