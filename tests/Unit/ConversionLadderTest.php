<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Unit;

use Voodflow\Vmedia\Support\ConversionLadder;
use Voodflow\Vmedia\Tests\TestCase;

class ConversionLadderTest extends TestCase
{
    public function test_defaults_include_thumb_and_display_ladder(): void
    {
        $keys = array_column(ConversionLadder::defaults(), 'key');

        $this->assertContains('thumb', $keys);
        $this->assertContains('sm', $keys);
        $this->assertContains('md', $keys);
        $this->assertContains('lg', $keys);
        $this->assertContains('xl', $keys);
        $this->assertSame('thumb', ConversionLadder::thumbKey());
        $this->assertSame('lg', ConversionLadder::defaultDisplayKey());
    }

    public function test_normalize_drops_invalid_keys_and_sorts_by_width(): void
    {
        $normalized = ConversionLadder::normalize([
            ['key' => 'LG', 'label' => 'Large', 'width' => 2048, 'role' => 'display'],
            ['key' => '../evil', 'width' => 100],
            ['key' => 'sm', 'width' => 512, 'format' => 'WEBP', 'role' => 'display'],
            ['key' => 'thumb', 'width' => 400, 'height' => 400, 'role' => 'thumb'],
        ]);

        $this->assertSame(['thumb', 'sm', 'lg'], array_column($normalized, 'key'));
        $this->assertSame('webp', $normalized[1]['format']);
        $this->assertSame(ConversionLadder::ROLE_THUMB, $normalized[0]['role']);
    }
}
