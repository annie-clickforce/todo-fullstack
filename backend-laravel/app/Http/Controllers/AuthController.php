<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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
