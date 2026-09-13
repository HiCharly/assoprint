<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_responses_carry_the_security_headers()
    {
        $response = $this->get(route('login'));

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'same-origin');
    }

    public function test_the_content_security_policy_forbids_inline_and_third_party_scripts()
    {
        $policy = $this->get(route('login'))->headers->get('Content-Security-Policy');

        $this->assertIsString($policy);
        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[^']+'/", $policy);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
    }

    public function test_the_inline_theme_script_carries_the_nonce_of_the_response()
    {
        $response = $this->get(route('login'));

        preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $matches);

        $response->assertSee('<script nonce="'.$matches[1].'">', false);
    }
}
