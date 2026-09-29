<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Get authenticated user profile
     */
    public function getProfile(Request $request)
    {
        try {
            $user = $request->user();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update user profile (including password)
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();
            
            // Validation rules
            $rules = [
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $user->id,
            ];
            
            // Add password validation rules if password is being changed
            $hasPassword = $request->has('password') && !empty($request->password);
            $hasCurrentPassword = $request->has('current_password') && !empty($request->current_password);
            
            if ($hasPassword || $hasCurrentPassword) {
                $rules['current_password'] = 'required|string';
                $rules['password'] = 'required|string|min:8|confirmed';
            }
            
            $validator = Validator::make($request->all(), $rules);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Update name
            if ($request->has('name')) {
                $user->name = $request->name;
            }
            
            // Update email
            if ($request->has('email')) {
                $user->email = $request->email;
            }
            
            // Update password if provided
            if ($hasPassword && $hasCurrentPassword) {
                // Verify current password
                if (!Hash::check($request->current_password, $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Current password is incorrect'
                    ], 422);
                }
                
                $user->password = Hash::make($request->password);
            }
            
            $user->save();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'message' => 'Profile updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin: Get all users
     */
    public function getAllUsers(Request $request)
    {
        try {
            // Get the authenticated user
            $user = $request->user();
            
            // Log for debugging
            Log::info('getAllUsers called', [
                'user_id' => $user ? $user->id : null,
                'user_email' => $user ? $user->email : null,
                'user_role' => $user ? $user->role : null
            ]);
            
            // Check if user exists
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            // Check if user is admin - DIRECT COMPARISON instead of using hasRole
            if ($user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required. Your role: ' . $user->role
                ], 403);
            }

            $users = User::all(['id', 'name', 'email', 'role', 'created_at']);
            
            return response()->json([
                'success' => true,
                'data' => $users
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in getAllUsers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin: Update user role
     */
    public function updateUserRole(Request $request, $id)
    {
        try {
            // Get the authenticated user
            $user = $request->user();
            
            // Check if user is admin - DIRECT COMPARISON
            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'role' => 'required|in:user,revenue_manager,expenditure_manager,admin'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $targetUser = User::find($id);
            
            if (!$targetUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
            
            // Prevent admin from changing their own role
            if ($targetUser->id === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot change your own role'
                ], 400);
            }
            
            $targetUser->role = $request->role;
            $targetUser->save();
            
            return response()->json([
                'success' => true,
                'message' => 'User role updated successfully',
                'data' => [
                    'id' => $targetUser->id,
                    'name' => $targetUser->name,
                    'email' => $targetUser->email,
                    'role' => $targetUser->role
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin: Delete user
     */
    public function deleteUser(Request $request, $id)
    {
        try {
            // Get the authenticated user
            $user = $request->user();
            
            // Check if user is admin - DIRECT COMPARISON
            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $targetUser = User::find($id);
            
            if (!$targetUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Prevent admin from deleting their own account
            if ($targetUser->id === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account'
                ], 400);
            }
            
            $targetUser->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user: ' . $e->getMessage()
            ], 500);
        }
    }
}