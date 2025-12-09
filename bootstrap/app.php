<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Define API middleware group
        $middleware->group('api', [
            \App\Http\Middleware\EnsureJsonRequest::class,
            // CORS
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Global HTTP request/response logging
        $middleware->append(\App\Http\Middleware\RequestResponseLogging::class);
    })
    ->withProviders([
        \App\Providers\OctaneServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always render JSON for API routes
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Validation errors => 422
        $exceptions->render(function (ValidationException $e, $request) {
            $errors = $e->errors();
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'The given data was invalid.',
                    'errors' => $errors,
                ],
            ], 422);
        });

        // Model not found => 404
        $exceptions->render(function (ModelNotFoundException $e, $request) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Resource not found.',
                ],
            ], 404);
        });

        // HTTP exceptions
        $exceptions->render(function (HttpExceptionInterface $e, $request) {
            $status = $e->getStatusCode();
            $message = $e instanceof NotFoundHttpException ? 'Not Found' : $e->getMessage();
            $message = $message ?: (string) \Symfony\Component\HttpFoundation\Response::$statusTexts[$status] ?? 'Error';

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'http_error',
                    'message' => $message,
                ],
            ], $status);
        });

        // Authentication / Authorization
        $exceptions->render(function (AuthenticationException|AuthorizationException $e, $request) {
            $status = $e instanceof AuthenticationException ? 401 : 403;
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $e instanceof AuthenticationException ? 'unauthenticated' : 'forbidden',
                    'message' => $e->getMessage() ?: ($e instanceof AuthenticationException ? 'Unauthenticated.' : 'Forbidden.'),
                ],
            ], $status);
        });
    })->create();
