<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaVault;
use Voodflow\Vmedia\Tests\TestCase;

/**
 * An SVG is a document, not an image.
 *
 * Served from the public disk and opened directly, it runs its own script in the site's
 * origin — so an upload form that accepts SVG and stores the bytes verbatim is a stored
 * XSS hole, reachable by anyone allowed to add media. SVG stays accepted (logos are the
 * common case, and rejecting them breaks real sites), so it has to be cleaned on the way in.
 */
final class SvgUploadSanitizationTest extends TestCase
{
    private const HOSTILE_SVG = <<<'SVG'
<?xml version="1.0"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="64" height="64">
  <script>alert(document.domain)</script>
  <rect width="64" height="64" fill="#0af" onload="alert(1)" onclick="alert(2)"/>
  <a xlink:href="javascript:alert(3)"><text x="4" y="20">click</text></a>
  <foreignObject><body xmlns="http://www.w3.org/1999/xhtml"><img src="x" onerror="alert(4)"/></body></foreignObject>
  <text style="behavior:url(#x);background:expression(alert(5))">styled</text>
</svg>
SVG;

    private function upload(string $svg, string $name = 'logo.svg'): string
    {
        Storage::fake('public');
        MediaGallery::default();
        $this->actingAsUser();

        $response = $this->postJson(route('vmedia.media.upload'), [
            'file' => UploadedFile::fake()->createWithContent($name, $svg),
        ]);

        $response->assertCreated();

        $media = MediaVault::current()
            ->media()
            ->latest('id')
            ->firstOrFail();

        return Storage::disk('public')->get($media->getPathRelativeToRoot());
    }

    public function test_a_stored_svg_carries_no_script_element(): void
    {
        $stored = $this->upload(self::HOSTILE_SVG);

        $this->assertStringNotContainsStringIgnoringCase('<script', $stored);
        $this->assertStringNotContainsString('alert(document.domain)', $stored);
    }

    public function test_a_stored_svg_carries_no_event_handler_attributes(): void
    {
        $stored = $this->upload(self::HOSTILE_SVG);

        $this->assertStringNotContainsStringIgnoringCase('onload=', $stored);
        $this->assertStringNotContainsStringIgnoringCase('onclick=', $stored);
        $this->assertStringNotContainsStringIgnoringCase('onerror=', $stored);
    }

    public function test_a_stored_svg_carries_no_script_urls_or_embedded_documents(): void
    {
        $stored = $this->upload(self::HOSTILE_SVG);

        $this->assertStringNotContainsStringIgnoringCase('javascript:', $stored);
        $this->assertStringNotContainsStringIgnoringCase('foreignobject', $stored);
        $this->assertStringNotContainsStringIgnoringCase('expression(', $stored);
        $this->assertStringNotContainsStringIgnoringCase('behavior:', $stored);
    }

    public function test_the_drawing_itself_survives_cleaning(): void
    {
        // Sanitizing is only acceptable if it is not silently destroying artwork.
        $stored = $this->upload(self::HOSTILE_SVG);

        $this->assertStringContainsString('<svg', $stored);
        $this->assertStringContainsString('<rect', $stored);
        $this->assertStringContainsString('#0af', $stored);
    }

    public function test_an_svg_referencing_its_own_defs_keeps_working(): void
    {
        // Fragment hrefs are how an SVG points at its own gradients; dropping them would
        // turn legitimate logos into blank boxes.
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="32" height="32">
  <defs><linearGradient id="g"><stop offset="0" stop-color="#fff"/></linearGradient></defs>
  <rect width="32" height="32" fill="url(#g)"/>
  <use xlink:href="#g"/>
</svg>
SVG;

        $stored = $this->upload($svg, 'gradient.svg');

        $this->assertStringContainsString('linearGradient', $stored);
        $this->assertStringContainsString('#g', $stored);
    }

    public function test_a_raster_upload_is_left_untouched(): void
    {
        Storage::fake('public');
        MediaGallery::default();
        $this->actingAsUser();

        $this->postJson(route('vmedia.media.upload'), [
            'file' => UploadedFile::fake()->image('photo.png', 12, 12),
        ])->assertCreated();

        $media = MediaVault::current()->media()->latest('id')->firstOrFail();

        $this->assertSame('image/png', $media->mime_type);
        $this->assertGreaterThan(0, $media->size);
    }
}
