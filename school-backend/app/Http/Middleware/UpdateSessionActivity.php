<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\UserSession;

class UpdateSessionActivity
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $user = $request->user();
        if ($user && $user->currentAccessToken()) {
            $tokenId = $user->currentAccessToken()->id;
            
            UserSession::where('user_id', $user->id)
                ->where('token_id', $tokenId)
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('last_active_at')
                      ->orWhere('last_active_at', '<', now()->subMinutes(1));
                })
                ->update(['last_active_at' => now()]);
        }

        return $response;
    }
}
