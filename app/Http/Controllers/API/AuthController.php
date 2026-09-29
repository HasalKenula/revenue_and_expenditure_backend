<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // <--- FIX 1: Import Auth
use App\Traits\LogsUserActivity;

class AuthController extends Controller
{
    use LogsUserActivity;

    // Register
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'sometimes|in:user,revenue_manager,expenditure_manager,admin'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        if ($request->has('role') && $request->role === 'admin') {
            if (!$request->user() || $request->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only administrators can create admin accounts'
                ], 403);
            }
        }

        $userData = $request->all();
        $userData['password'] = Hash::make($request->password);
        $userData['role'] = $request->role ?? 'user';

        $user = User::create($userData);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User Registered Successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ],
            'token' => $token
        ], 201);
    }

    // Login
    public function login(Request $request)
    {
        \Log::info('=== LOGIN ATTEMPT STARTED ===', [
            'email' => $request->email,
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'timestamp' => now()->toDateTimeString()
        ]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            \Log::warning('Login validation failed', [
                'email' => $request->email,
                'errors' => $validator->errors()->toArray()
            ]);
            
            $this->logFailedLogin($request->email, $request->ip());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        
        if (!$user || !Hash::check($request->password, $user->password)) {
            \Log::warning('Login failed - invalid credentials', [
                'email' => $request->email,
                'user_found' => $user ? 'yes' : 'no'
            ]);
            
            $this->logFailedLogin($request->email, $request->ip());
            return response()->json([
                'success' => false,
                'message' => 'Invalid Login Credentials'
            ], 401);
        }

        // --- FIX 2: Authenticate the user for the current request BEFORE logging ---
        Auth::login($user); 

        \Log::info('User authenticated successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'name' => $user->name
        ]);

        // --- FIX 3: Pass $user directly to the trait method ---
        \Log::info('Calling logUserActivity for login', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);
        
        $this->logUserActivity('login', $user);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login Successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ],
            'token' => $token
        ], 200);
    }

    // Logout
    public function logout(Request $request)
    {
        $user = $request->user();
        
        \Log::info('=== LOGOUT ATTEMPT STARTED ===', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'timestamp' => now()->toDateTimeString()
        ]);
        
        \Log::info('Calling logUserActivity for logout', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);
        
        // --- FIX 4: Pass $user directly to the trait method ---
        $this->logUserActivity('logout', $user);
        
        \Log::info('logUserActivity completed for logout', [
            'user_id' => $user->id
        ]);
        
        $user->currentAccessToken()->delete();
        
        \Log::info('User logged out successfully', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ], 200);
    }

    // Get Profile
    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'role' => $request->user()->role,
                'created_at' => $request->user()->created_at
            ]
        ]);
    }
}