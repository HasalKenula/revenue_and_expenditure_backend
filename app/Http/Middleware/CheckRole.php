<?php

// namespace App\Http\Middleware;

// use Closure;
// use Illuminate\Http\Request;
// use Symfony\Component\HttpFoundation\Response;

// class CheckRole
// {
//     /**
//      * Handle an incoming request.
//      *
//      * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
//      */
//     public function handle(Request $request, Closure $next, ...$roles): Response
//     {
//         // Check if user is authenticated
//         if (!$request->user()) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Unauthenticated. Please login first.'
//             ], 401);
//         }

//         // Check if user has the required role
//         if (!in_array($request->user()->role, $roles)) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Access denied. You do not have the required permissions.',
//                 'required_roles' => $roles,
//                 'your_role' => $request->user()->role
//             ], 403);
//         }

//         return $next($request);
//     }
// }

// app/Http/Middleware/CheckRole.php


namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        // Check if user has the required role
        if (!in_array($request->user()->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You do not have the required permissions.',
                'required_roles' => $roles,
                'your_role' => $request->user()->role
            ], 403);
        }

        return $next($request);
    }
}