<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->header('X-Inertia')) {
                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    $msg = $e->getMessage() === 'This action is unauthorized.' ? 'Acesso negado.' : $e->getMessage();
                    return \Inertia\Inertia::render('AccessDenied', ['message' => $msg])
                        ->toResponse($request)
                        ->setStatusCode(403);
                }

                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface && $e->getStatusCode() === 403) {
                    $msg = $e->getMessage() ?: 'Acesso negado.';
                    return \Inertia\Inertia::render('AccessDenied', ['message' => $msg])
                        ->toResponse($request)
                        ->setStatusCode(403);
                }
            }
        });
    })->create();
