<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckIfCeo
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            Log::info('User authenticated', ['user' => $user]);

            if ($user->role_id === 1) { // Assuming role ID 1 is for CEO
                return $next($request);
            } else {
                Log::warning('User is not a CEO', ['user' => $user]);
            }
        } else {
            Log::warning('User not authenticated');
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}

// check if user is authenticated
// tell me user is authentictaed and give me his details
// check is the user's role is equal to 1
// if no say user is not a ceo
// end