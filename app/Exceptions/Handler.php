<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class Handler extends ExceptionHandler
{
    /*
    |--------------------------------------------------------------------------
    | Return a JSON error response in a unified format.
    |--------------------------------------------------------------------------
    */
    protected function apiError(string $code, string $message, int $status, array $details = null, array $headers = [])
    {
        $payload = [
            'success' => false,
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
        ];

        if (! is_null($details)) {
            $payload['error']['details'] = $details;
        }

        return response()->json($payload, $status, $headers);
    }

    /*
    |--------------------------------------------------------------------------
    | Override for validation JSON shape.
    |--------------------------------------------------------------------------
    */
    protected function invalidJson($request, ValidationException $exception)
    {
        Log::info('invalidJson called');
        return $this->apiError(
            'VALIDATION_ERROR',
            'The given data was invalid.',
            $exception->status,
            $exception->errors()
        );
    }

    public function render($request, Throwable $e)
    {
        /*
        |--------------------------------------------------------------------------
        | Always return JSON for API routes (or when the client explicitly expects JSON)
        |--------------------------------------------------------------------------
        */
        if ($request->is('api/*') || $request->expectsJson()) {

            if ($e instanceof ValidationException) {
                return $this->apiError(
                    'VALIDATION_ERROR',
                    'The given data was invalid.',
                    $e->status,
                    $e->errors()
                );
            }

            if ($e instanceof AuthenticationException) {
                return $this->apiError('UNAUTHENTICATED', 'Authentication required.', 401);
            }

            if ($e instanceof AuthorizationException) {
                return $this->apiError('FORBIDDEN', 'You are not allowed to perform this action.', 403);
            }

            if ($e instanceof ModelNotFoundException) {
                $model = class_basename($e->getModel());
                return $this->apiError('NOT_FOUND', "{$model} not found.", 404);
            }

            if ($e instanceof ThrottleRequestsException) {
                $headers = [];
                if (method_exists($e, 'getHeaders')) {
                    $headers = $e->getHeaders();
                }
                return $this->apiError('TOO_MANY_REQUESTS', 'Too many requests. Please try again later.', 429, null, $headers);
            }

            if ($e instanceof HttpExceptionInterface) {
                return $this->apiError(
                    'HTTP_ERROR',
                    $e->getMessage() ?: 'HTTP error.',
                    $e->getStatusCode()
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Fallback
            |--------------------------------------------------------------------------
            */
            return $this->apiError('SERVER_ERROR', 'Something went wrong.', 500);
        }

        /*
        |--------------------------------------------------------------------------
        | Non-API: keep default behavior
        |--------------------------------------------------------------------------
        */
        return parent::render($request, $e);
    }
}
