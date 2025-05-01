<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable $exception
     * @return void
     *
     * @throws Throwable
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {

        if($request->expectsJson())
        {

            // detect instance
            if ($exception instanceof UnauthorizedHttpException) {
                // detect previous instance

                $ERR_CODE = null;

                if ($exception->getPrevious() instanceof TokenExpiredException) {

                    $ERR_CODE = "TOKEN_EXPIRED";

                } else if ($exception->getPrevious() instanceof TokenInvalidException) {

                    $ERR_CODE = "TOKEN_INVALID";

                } else if ($exception->getPrevious() instanceof TokenBlacklistedException) {

                    $ERR_CODE = "TOKEN_BLACKLISTED";

                } else {

                    $ERR_CODE = "TOKEN_NOT_PROVIDED";

                }

                return response()->json([
                    'success' => false,
                    'error_code' => $ERR_CODE,
                    'message' => $exception->getMessage(),
                ], $exception->getStatusCode());
            }

        }


        return parent::render($request, $exception);
    }
}
