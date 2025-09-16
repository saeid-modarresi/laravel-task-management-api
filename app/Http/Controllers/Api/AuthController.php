<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Get the user
        |--------------------------------------------------------------------------
        */
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'INVALID_CREDENTIALS', 'message' => 'Email or password is incorrect.'],
            ], 422);
        }

         /*
        |--------------------------------------------------------------------------
        | Check Sanctum: if installed, issue a token; otherwise just return the user
        |--------------------------------------------------------------------------
        */
        $token = method_exists($user, 'createToken') ? $user->createToken('api')->plainTextToken : null;

        return response()->json([
            'success' => true,
            'data' => [
                'user'  => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
                'token' => $token,
            ],
        ]);
    }
}
