<?php

namespace App\Http\Middleware\Auth;

use Closure;

class Auth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(!auth()->check()) {

            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHORIZED',
                'message' => 'You are not authenticated!',
            ], 401);

        } else {

            $user = auth()->user();

            if($user->IsActive == 0) {

                auth()->invalidate();

                return response()->json([
                    'success' => false,
                    'error_code' => 'ACCOUNT_BANNED',
                    'message' => 'Failed! Company or account was banned by admin!',
                ], 401);

            } elseif ($user->EmailVerified == 0) {


                if($user->IsSupportUser != 1) {

                    auth()->invalidate();

                    return response()->json([
                        'success' => false,
                        'error_code' => 'EMAIL_NOT_VERIFIED',
                        'message' => 'Failed! Your email was not verified!',
                    ], 401);

                }

            } if($user->IsDeleted == 1) {

                auth()->invalidate();

                return response()->json([
                    'success' => false,
                    'error_code' => 'ACCOUNT_DELETED',
                    'message' => 'Failed! Your account was deleted by admin!',
                ], 401);

            }

        }

        return $next($request);
    }
}
