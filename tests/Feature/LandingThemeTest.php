<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The public landing page is a static file in public/landing, so these checks read it
 * directly. They guard the theme hand-off that used to flash light-then-dark on load.
 */
class LandingThemeTest extends TestCase
{
    private function landing(): string
    {
        return file_get_contents(public_path('landing/index.html'));
    }

    public function test_theme_is_applied_before_the_first_stylesheet(): void
    {
        $html = $this->landing();

        $script = strpos($html, 'internpulse-theme');
        $stylesheet = strpos($html, 'rel="stylesheet"');

        $this->assertNotFalse($script, 'The landing page must read the saved theme.');
        $this->assertNotFalse($stylesheet);
        $this->assertLessThan(
            $stylesheet,
            $script,
            'The theme script must run before any stylesheet, otherwise the first paint uses the wrong theme.'
        );
    }

    public function test_html_carries_a_default_theme_attribute(): void
    {
        $this->assertMatchesRegularExpression('/<html[^>]*data-theme="(dark|light)"/', $this->landing());
    }

    public function test_landing_header_has_its_own_stylesheet_after_theme_css(): void
    {
        $html = $this->landing();

        $this->assertStringContainsString('class="public-theme landing-page"', $html);
        $this->assertLessThan(
            strpos($html, 'landing-theme.css'),
            strpos($html, 'theme.css'),
            'landing-theme.css must load after theme.css so it wins the cascade.'
        );
    }

    public function test_theme_css_does_not_restyle_the_landing_header(): void
    {
        $theme = file_get_contents(public_path('theme.css'));

        $this->assertStringNotContainsString(
            '.public-theme header{',
            $theme,
            'theme.css header rules belong to the sign-in page; scope them with .login-page.'
        );
        $this->assertStringNotContainsString('.public-theme header .logo{', $theme);
    }
}
