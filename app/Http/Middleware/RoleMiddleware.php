<?php

namespace App\Http\Middleware;

use BackedEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $role = $user?->role instanceof BackedEnum ? $user->role->value : $user?->role;

        if (! $user || ! in_array($role, $roles, true)) {
            return response()->json([
                'message' => 'Access denied',
            ], 403);
        }
        
        return $next($request);
    }
}
