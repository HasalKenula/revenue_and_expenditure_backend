<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    //
     public function register(Request $request){
        $validator = Validator::make($request->all(),[
            'name'=>'required|string|max:255',
            'email'=>'required|string|email|unique:users',
            'password'=>'required|string|min:8'
        ]);

        if($validator->fails()){
            return response()->json([
                'message'=>$validator->errors()
            ],400);
        }
    

        $user = User::create($request->all());

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'=>'User Registered Successfully',
            'user'=>$user,
            'token'=>$token
        ], 201);

    }

      public function login(Request $request){
        $validator = Validator::make($request->all(),[           
            'email'=>'required|string|email',
            'password'=>'required|string'
        ]);

        if($validator->fails()){
            return response()->json([
                'message'=>$validator->errors()
            ],400);
        }

        $user = User::where('email', $request->email)->first();

        if(!$user || !Hash::check($request->password, $user->password)){
            return response()->json([
                'message'=>"Invalid Login Credential"
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'=>'Logi Successfull',
            'user'=>$user,
            'token'=>$token
        ],200);
    }

     //Logout API
    public function logout(Request $request){
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'message'=>'Logged out successfully'
        ], 200);
    }

   

}
