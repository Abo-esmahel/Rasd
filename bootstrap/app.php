<?php

use App\Http\Middleware\Api\AuthenticateApi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.api' => AuthenticateApi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        
        $exceptions->report(function (MethodNotAllowedHttpException $e) {
            try {
                $req = request();
                Log::warning('[405 FORENSIC] MethodNotAllowed', [
                    'url' => $req->fullUrl(),
                    'path' => $req->path(),
                    'method' => $req->method(),
                    'ip' => $req->ip(),
                    'allow' => $e->getHeaders()['Allow'] ?? $e->getHeaders()['allow'] ?? null,
                    'content_type' => $req->header('Content-Type'),
                    'accept' => $req->header('Accept'),
                    'x_requested_with' => $req->header('X-Requested-With'),
                    'has_files' => $req->hasFile('files'),
                    'files_count' => is_array($req->file('files')) ? count($req->file('files')) : ($req->hasFile('files') ? 1 : 0),
                    'all_input_keys' => array_keys($req->all()),
                    'headers' => $req->headers->all(),
                ]);
            } catch (\Throwable $ex) {
                Log::warning('[405 FORENSIC] logging failed: '.$ex->getMessage());
            }
        });
    })->create();
