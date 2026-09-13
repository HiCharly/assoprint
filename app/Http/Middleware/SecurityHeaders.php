<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ajoute les en-têtes de sécurité à toutes les réponses.
 *
 * La Content-Security-Policy s'appuie sur un nonce régénéré à chaque requête,
 * qui autorise le script inline de détection du thème et les balises produites
 * par Vite, sans jamais ouvrir `unsafe-inline` aux scripts.
 *
 * `style-src` conserve en revanche `unsafe-inline` : les composants Radix/shadcn
 * positionnent leurs popovers via des attributs `style`, qu'un nonce ne peut pas
 * couvrir. Comme un nonce dans `style-src` désactiverait `unsafe-inline` aux
 * yeux du navigateur, les deux ne peuvent pas cohabiter.
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        foreach ($this->headers($nonce) as $header => $value) {
            $response->headers->set($header, $value);
        }

        return $response;
    }

    /**
     * The security headers to add to every response.
     *
     * @return array<string, string>
     */
    private function headers(string $nonce): array
    {
        return [
            'Content-Security-Policy' => $this->contentSecurityPolicy($nonce),
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), interest-cohort=()',
        ];
    }

    /**
     * Build the Content-Security-Policy header.
     */
    private function contentSecurityPolicy(string $nonce): string
    {
        // En développement, les assets sont servis par le serveur Vite et le
        // rafraîchissement à chaud passe par un websocket : ces origines n'ont
        // évidemment rien à faire dans la politique appliquée en production.
        $vite = app()->environment('local')
            ? ' http://localhost:5173 http://127.0.0.1:5173'
            : '';

        $viteSocket = app()->environment('local')
            ? ' ws://localhost:5173 ws://127.0.0.1:5173'
            : '';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'{$vite}",
            "style-src 'self' 'unsafe-inline'{$vite}",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "connect-src 'self'{$vite}{$viteSocket}",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
