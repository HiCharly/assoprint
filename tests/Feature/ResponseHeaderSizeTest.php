<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ResponseHeaderSizeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Taille au-delà de laquelle nginx refuse une réponse FastCGI par défaut.
     *
     * Dépasser ce seuil provoque un 502 sans la moindre trace côté Laravel :
     * PHP a répondu, c'est nginx qui coupe. La marge laissée ici couvre les
     * cookies de session réels, plus gros qu'en test.
     */
    private const NGINX_DEFAULT_BUFFER = 4096;

    private function headerSize(TestResponse $response): int
    {
        $size = 0;

        foreach ($response->headers->all() as $name => $values) {
            foreach ($values as $value) {
                // « Nom: valeur\r\n »
                $size += strlen($name) + strlen((string) $value) + 4;
            }
        }

        return $size;
    }

    public function test_the_login_page_headers_stay_under_the_default_nginx_buffer()
    {
        $size = $this->headerSize($this->get(route('login')));

        $this->assertLessThan(
            self::NGINX_DEFAULT_BUFFER / 2,
            $size,
            "Les en-têtes de la page de connexion pèsent {$size} octets. Au-delà de "
            .self::NGINX_DEFAULT_BUFFER.' octets, nginx répond 502.',
        );
    }

    public function test_the_heaviest_page_headers_stay_under_the_default_nginx_buffer()
    {
        // Le tableau de bord charge le plus d'assets : c'est lui qui ferait
        // déborder l'en-tête en premier.
        $user = User::factory()->create();

        $size = $this->headerSize($this->actingAs($user)->get(route('dashboard')));

        $this->assertLessThan(
            self::NGINX_DEFAULT_BUFFER / 2,
            $size,
            "Les en-têtes du tableau de bord pèsent {$size} octets. Au-delà de "
            .self::NGINX_DEFAULT_BUFFER.' octets, nginx répond 502.',
        );
    }
}
