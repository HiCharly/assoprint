<?php

use App\Http\Middleware\EnsureUserHasChosenPassword;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
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
        // Le seul chemin vers l'application passe par cloudflared, qui parle à
        // nginx depuis la boucle locale : c'est donc lui, et lui seul, dont les
        // en-têtes X-Forwarded-* font foi. Sans cela Laravel se croit en HTTP et
        // génère des URL en clair derrière le tunnel, et le throttling de
        // connexion compte toutes les tentatives sur la même adresse — celle du
        // proxy — au lieu de celle de chaque visiteur.
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // AddLinkHeadersForPreloadedAssets, fourni par le starter kit, n'est pas
        // repris : il liste tous les assets de la page dans un en-tête Link, que
        // notre CSP alourdit d'un nonce par entrée. L'en-tête dépassait les 4 Ko
        // réservés par défaut aux réponses FastCGI, et nginx répondait 502 sans
        // que PHP soit en cause. Le navigateur reçoit de toute façon les mêmes
        // préchargements dans le <head>, et l'avance apportée suppose les Early
        // Hints, que nginx n'active pas.
        $middleware->web(append: [
            SecurityHeaders::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'admin' => EnsureUserIsAdmin::class,
            'password.chosen' => EnsureUserHasChosenPassword::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
