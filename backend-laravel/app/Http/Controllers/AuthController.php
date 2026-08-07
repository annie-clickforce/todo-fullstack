<?php

namespace App\Http\Controllers;

use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = strtolower($request->input('email'));

        if (User::where('email', $email)->exists()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['email' => ['Email is already registered']],
            ], 422);
        }

        $user = User::create([
            'name' => trim($request->input('name')),
            'email' => $email,
            'password_hash' => Hash::make($request->input('password')),
        ]);

        return response()->json($this->formatUser($user), 201);
    }

    public function login(Request $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');

        if (!is_string($email) || !is_string($password)) {
            return response()->json(['message' => 'Invalid email or password'], 401);
        }

        $user = User::where('email', strtolower($email))->first();

        if (!$user || !Hash::check($password, $user->password_hash)) {
            return response()->json(['message' => 'Invalid email or password'], 401);
        }

        $request->session()->regenerate();
        Auth::login($user);

        return response()->json($this->formatUser($user));
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return response()->json($this->formatUser($request->user()));
    }

    public function forgotPassword(Request $request)
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $user = User::where('email', strtolower($email))->first();

            if ($user) {
                $token = Str::random(64);

                PasswordResetToken::create([
                    'user_id' => $user->id,
                    'token_hash' => hash('sha256', $token),
                    'expires_at' => now()->addHour(),
                ]);

                // No mail transport configured for this MVP; the token would
                // normally be emailed to the user as a reset link.
            }
        }

        return response()->noContent(202);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tokenHash = hash('sha256', $request->input('token'));

        $resetToken = PasswordResetToken::where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$resetToken) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['token' => ['Token is invalid or expired']],
            ], 422);
        }

        $user = User::find($resetToken->user_id);
        $user->password_hash = Hash::make($request->input('password'));
        $user->save();

        $resetToken->used_at = now();
        $resetToken->save();

        PasswordResetToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('id', '!=', $resetToken->id)
            ->update(['used_at' => now()]);

        return response()->noContent();
    }

    private function formatUser(User $user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'createdAt' => $user->created_at,
            'updatedAt' => $user->updated_at,
        ];
    }
}
