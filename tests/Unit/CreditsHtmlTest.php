<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Voodflow\Vmedia\Support\CreditsHtml;

class CreditsHtmlTest extends TestCase
{
    public function test_plain_text_is_escaped(): void
    {
        $this->assertSame(
            'Photo by Jeremy &amp; Co',
            CreditsHtml::sanitize('Photo by Jeremy & Co'),
        );
    }

    public function test_unsplash_style_links_are_kept(): void
    {
        $raw = 'Photo by <a href="https://unsplash.com/@jeremythomasphoto?utm_source=unsplash">Jeremy Thomas</a>'
            .' on <a href="https://unsplash.com/photos/blue-and-purple-galaxy-digital-wallpaper-E0AHdsENmDg?utm_source=unsplash">Unsplash</a>';

        $html = CreditsHtml::sanitize($raw);

        $this->assertStringContainsString('Jeremy Thomas', $html);
        $this->assertStringContainsString('Unsplash', $html);
        $this->assertStringContainsString('href="https://unsplash.com/@jeremythomasphoto?utm_source=unsplash"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringNotContainsString('<script', strtolower($html));
    }

    public function test_non_http_links_are_stripped_to_text(): void
    {
        $html = CreditsHtml::sanitize('See <a href="javascript:alert(1)">here</a> now');

        $this->assertStringNotContainsString('<a', $html);
        $this->assertStringContainsString('here', $html);
        $this->assertStringContainsString('now', $html);
    }

    public function test_empty_returns_empty(): void
    {
        $this->assertSame('', CreditsHtml::sanitize(null));
        $this->assertSame('', CreditsHtml::sanitize('   '));
    }
}
