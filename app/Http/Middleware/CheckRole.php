<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login first.'
            ], 401);
        }

        // Get the user's role
        $userRole = $request->user()->role;
        
        // Debug logging - this will help us see what's happening
        Log::info('CheckRole Middleware', [
            'user_email' => $request->user()->email,
            'user_role' => $userRole,
            'required_roles' => $roles,
            'url' => $request->fullUrl()
        ]);
        
        // If user is admin, allow access to ANY route
        if ($userRole === 'admin') {
            return $next($request);
        }
        
        // Check if user has the required role
        if (!in_array($userRole, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You do not have the required permissions.',
                'required_roles' => $roles,
                'your_role' => $userRole,
                'user_email' => $request->user()->email
            ], 403);
        }

        return $next($request);
    }
}