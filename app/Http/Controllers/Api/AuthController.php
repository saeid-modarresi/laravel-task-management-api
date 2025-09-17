<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Validation (outside try-catch so Laravel can handle validation errors)
        |--------------------------------------------------------------------------
        */
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {

            /*
            |--------------------------------------------------------------------------
            | Get the user
            |--------------------------------------------------------------------------
            */
            $user = User::where('email', $data['email'])->first();

            if (! $user || ! Hash::check($data['password'], $user->password)) {
                // Log failed login attempt for security monitoring
                Log::warning('Failed login attempt', [
                    'email' => $data['email'],
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);

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

            /*
            |--------------------------------------------------------------------------
            | Log successful login
            |--------------------------------------------------------------------------
            */
            Log::info('User logged in successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'user'  => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
                    'token' => $token,
                ],
            ]);
        } catch (Exception $e) {

            /*
            |--------------------------------------------------------------------------
            | Log unexpected errors
            |--------------------------------------------------------------------------
            */
            Log::error('Login error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->only(['email'])
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'Unable to process login request. Please try again.'
                ]
            ], 500);
        }
    }

    public function register(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Validation (outside try-catch so Laravel can handle validation errors)
        |--------------------------------------------------------------------------
        */
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
        ]);

        try {

            /*
            |--------------------------------------------------------------------------
            | Create the user using database transaction for data integrity
            |--------------------------------------------------------------------------
            */
            /** @var User $user */
            $user = DB::transaction(function () use ($data) {
                return User::create([
                    'name'     => $data['name'],
                    'email'    => $data['email'],
                    'password' => Hash::make($data['password']),
                ]);
            });

            /*
            |--------------------------------------------------------------------------
            | Issue token if Sanctum is installed
            |--------------------------------------------------------------------------
            */
            $token = null;
            if (method_exists($user, 'createToken')) {
                try {
                    $token = $user->createToken('api')->plainTextToken;
                } catch (Exception $e) {
             
                    /*
                    |--------------------------------------------------------------------------
                    | Log token creation error but don't fail the registration
                    |--------------------------------------------------------------------------
                    */
                    Log::warning('Token creation failed during registration', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Log successful registration
            |--------------------------------------------------------------------------
            */
            Log::info('User registered successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'user'  => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
                    'token' => $token,
                ],
            ], 201);
        } catch (Exception $e) {

            /*
            |--------------------------------------------------------------------------
            | Log registration error
            |--------------------------------------------------------------------------
            */
            Log::error('Registration error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->only(['name', 'email'])
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REGISTRATION_ERROR',
                    'message' => 'Unable to create account. Please try again.'
                ]
            ], 500);
        }
    }
}
