<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tunnel_is_believed_when_it_says_the_visitor_arrived_in_https()
    {
        // Ce que cloudflared envoie à nginx depuis la boucle locale.
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'impression.exemple.fr',
        ])->get('/');

        // Sans proxy de confiance, la redirection partirait en http : le
        // navigateur ferait un aller-retour en clair avant d'être renvoyé en
        // HTTPS par Cloudflare, et les assets seraient chargés en clair.
        $response->assertRedirect('https://impression.exemple.fr/login');
    }

    public function test_an_unknown_proxy_is_not_believed()
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.7',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'imprimante-pirate.exemple.fr',
        ])->get('/');

        // Un en-tête X-Forwarded-Host forgé ne doit pas pouvoir détourner la
        // redirection vers un autre domaine.
        $response->assertRedirect(route('login'));
    }
}
