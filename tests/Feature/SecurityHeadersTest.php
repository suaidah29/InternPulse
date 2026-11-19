<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pages_carry_the_hardening_headers(): void
    {
        $response = $this->get('/login')->assertOk();

        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('camera=(self)', $response->headers->get('Permissions-Policy'));
        $this->assertSame('same-origin', $response->headers->get('Cross-Origin-Opener-Policy'));
    }

    public function test_the_policy_locks_down_framing_and_object_embeds(): void
    {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
    }

    public function test_the_policy_still_allows_the_assets_the_pages_actually_load(): void
    {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');

        // The hero clip behind the sign-in card is hosted on a CDN.
        $this->assertStringContainsString('media-src \'self\' https://d8j0ntlcm91z4.cloudfront.net', $csp);
        // Fonts come from Google Fonts, an online webfont host and cdnjs.
        $this->assertStringContainsString('fonts.googleapis.com', $csp);
        $this->assertStringContainsString('fonts.gstatic.com', $csp);
        $this->assertStringContainsString('cdnjs.cloudflare.com', $csp);
    }

    public function test_the_inline_theme_script_matches_the_policy_nonce(): void
    {
        $response = $this->get('/login')->assertOk();

        preg_match("/'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy'), $header);
        preg_match('/<script nonce="([^"]+)">/', $response->content(), $inline);

        $this->assertNotEmpty($header[1] ?? null, 'The policy must carry a nonce.');
        $this->assertSame($header[1], $inline[1] ?? null, 'The inline script must use the same nonce.');
    }

    public function test_each_response_gets_its_own_nonce(): void
    {
        preg_match("/'nonce-([^']+)'/", $this->get('/login')->headers->get('Content-Security-Policy'), $first);
        preg_match("/'nonce-([^']+)'/", $this->get('/login')->headers->get('Content-Security-Policy'), $second);

        $this->assertNotSame($first[1], $second[1]);
    }

    public function test_the_two_factor_page_inline_script_is_nonce_tagged(): void
    {
        $user = User::factory()->create(['role' => 'intern']);

        $response = $this->withSession(['login.two_factor_user_id' => $user->id])->get('/login/two-factor');

        $response->assertOk();
        $this->assertStringContainsString('<script nonce="', $response->content());
        $this->assertStringContainsString("script-src 'self' 'nonce-", $response->headers->get('Content-Security-Policy'));
    }

    public function test_no_inline_script_in_the_views_is_missing_a_nonce(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            preg_match_all('/<script(?![^>]*\bsrc=)(?![^>]*\bnonce=)[^>]*>/i', file_get_contents($file->getPathname()), $matches);

            foreach ($matches[0] as $tag) {
                $offenders[] = $file->getFilename().': '.$tag;
            }
        }

        $this->assertSame([], $offenders, 'Inline scripts blocked by the CSP: '.implode(', ', $offenders));
    }

    public function test_hsts_only_appears_over_https(): void
    {
        $this->assertNull($this->get('/login')->headers->get('Strict-Transport-Security'));

        $secure = $this->get('https://localhost/login');
        $this->assertSame('max-age=31536000; includeSubDomains', $secure->headers->get('Strict-Transport-Security'));
    }

    public function test_error_pages_carry_the_headers_too(): void
    {
        $user = User::factory()->create(['role' => 'intern']);

        $response = $this->actingAs($user)->get('/workspace/tasks/999999');

        $response->assertNotFound();
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
    }
}
