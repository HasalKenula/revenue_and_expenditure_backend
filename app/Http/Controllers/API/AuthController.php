<?php

// namespace App\Http\Controllers\API;

// use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Validator;
// use App\Models\User;
// use Illuminate\Support\Facades\Hash;

// class AuthController extends Controller
// {
//     //created register
//      public function register(Request $request){
//         $validator = Validator::make($request->all(),[
//             'name'=>'required|string|max:255',
//             'email'=>'required|string|email|unique:users',
//             'password'=>'required|string|min:8'
//         ]);

//         if($validator->fails()){
//             return response()->json([
//                 'message'=>$validator->errors()
//             ],400);
//         }
    

//         $user = User::create($request->all());

//         $token = $user->createToken('auth_token')->plainTextToken;

//         return response()->json([
//             'message'=>'User Registered Successfully',
//             'user'=>$user,
//             'token'=>$token
//         ], 201);

//     }

//     //created login
//       public function login(Request $request){
//         $validator = Validator::make($request->all(),[           
//             'email'=>'required|string|email',
//             'password'=>'required|string'
//         ]);

//         if($validator->fails()){
//             return response()->json([
//                 'message'=>$validator->errors()
//             ],400);
//         }

//         $user = User::where('email', $request->email)->first();

//         if(!$user || !Hash::check($request->password, $user->password)){
//             return response()->json([
//                 'message'=>"Invalid Login Credential"
//             ], 401);
//         }

//         $token = $user->createToken('auth_token')->plainTextToken;

//         return response()->json([
//             'message'=>'Logi Successfull',
//             'user'=>$user,
//             'token'=>$token
//         ],200);
//     }

//      //Logout API
//     public function logout(Request $request){
//         $request->user()->currentAccessToken()->delete();
//         return response()->json([
//             'message'=>'Logged out successfully'
//         ], 200);
//     }

   

// }



// namespace App\Http\Controllers\API;

// use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Validator;
// use App\Models\User;
// use Illuminate\Support\Facades\Hash;

// class AuthController extends Controller
// {
//     // Register
//     public function register(Request $request)
//     {
//         $validator = Validator::make($request->all(), [
//             'name' => 'required|string|max:255',
//             'email' => 'required|string|email|unique:users',
//             'password' => 'required|string|min:8',
//             'role' => 'sometimes|in:user,admin'
//         ]);

//         if ($validator->fails()) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Validation failed',
//                 'errors' => $validator->errors()
//             ], 400);
//         }

//         $userData = $request->all();
//         $userData['password'] = Hash::make($request->password);
//         $userData['role'] = $request->role ?? 'user';

//         $user = User::create($userData);

//         $token = $user->createToken('auth_token')->plainTextToken;

//         return response()->json([
//             'success' => true,
//             'message' => 'User Registered Successfully',
//             'user' => [
//                 'id' => $user->id,
//                 'name' => $user->name,
//                 'email' => $user->email,
//                 'role' => $user->role
//             ],
//             'token' => $token
//         ], 201);
//     }

//     // Login
//     public function login(Request $request)
//     {
//         $validator = Validator::make($request->all(), [
//             'email' => 'required|string|email',
//             'password' => 'required|string'
//         ]);

//         if ($validator->fails()) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Validation failed',
//                 'errors' => $validator->errors()
//             ], 400);
//         }

//         $user = User::where('email', $request->email)->first();

//         if (!$user || !Hash::check($request->password, $user->password)) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Invalid Login Credentials'
//             ], 401);
//         }

//         $token = $user->createToken('auth_token')->plainTextToken;

//         return response()->json([
//             'success' => true,
//             'message' => 'Login Successful',
//             'user' => [
//                 'id' => $user->id,
//                 'name' => $user->name,
//                 'email' => $user->email,
//                 'role' => $user->role
//             ],
//             'token' => $token
//         ], 200);
//     }

//     // Logout
//     public function logout(Request $request)
//     {
//         $request->user()->currentAccessToken()->delete();
//         return response()->json([
//             'success' => true,
//             'message' => 'Logged out successfully'
//         ], 200);
//     }

//     // Get Profile
//     public function profile(Request $request)
//     {
//         return response()->json([
//             'success' => true,
//             'data' => [
//                 'id' => $request->user()->id,
//                 'name' => $request->user()->name,
//                 'email' => $request->user()->email,
//                 'role' => $request->user()->role,
//                 'created_at' => $request->user()->created_at
//             ]
//         ]);
//     }
// }


// app/Http/Controllers/API/AuthController.php


namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Register
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'sometimes|in:user,revenue_manager,expenditure_manager'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
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
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Login Credentials'
            ], 401);
        }

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
        $request->user()->currentAccessToken()->delete();
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