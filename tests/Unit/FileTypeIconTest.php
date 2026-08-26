<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Voodflow\Vmedia\Support\FileTypeIcon;

class FileTypeIconTest extends TestCase
{
    public function test_resolves_common_extensions(): void
    {
        $this->assertSame('pdf', FileTypeIcon::key(null, 'report.PDF'));
        $this->assertSame('zip', FileTypeIcon::key(null, 'archive.zip'));
        $this->assertSame('doc', FileTypeIcon::key(null, 'brief.docx'));
        $this->assertSame('xls', FileTypeIcon::key(null, 'sheet.xlsx'));
        $this->assertSame('csv', FileTypeIcon::key(null, 'data.csv'));
        $this->assertSame('txt', FileTypeIcon::key(null, 'notes.txt'));
        $this->assertSame('video', FileTypeIcon::key(null, 'clip.mp4'));
        $this->assertSame('image', FileTypeIcon::key(null, 'photo.jpg'));
    }

    public function test_resolves_from_mime_when_extension_missing(): void
    {
        $this->assertSame('pdf', FileTypeIcon::key('application/pdf', 'untitled'));
        $this->assertSame('zip', FileTypeIcon::key('application/x-zip-compressed', 'blob'));
        $this->assertSame(
            'doc',
            FileTypeIcon::key('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'blob'),
        );
    }

    public function test_falls_back_to_generic_file_icon(): void
    {
        $this->assertSame(FileTypeIcon::FALLBACK, FileTypeIcon::key('application/octet-stream', 'mystery.bin'));
        $this->assertSame(FileTypeIcon::FALLBACK, FileTypeIcon::key(null, null, 'file'));
    }

    public function test_label_prefers_extension(): void
    {
        $this->assertSame('DOCX', FileTypeIcon::label('doc', 'brief.docx'));
        $this->assertSame('PDF', FileTypeIcon::label('pdf', null));
        $this->assertSame('FILE', FileTypeIcon::label(FileTypeIcon::FALLBACK, null));
    }

    public function test_data_uri_is_svg(): void
    {
        $uri = FileTypeIcon::dataUri('pdf', 'PDF');

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
        $decoded = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')), true);
        $this->assertIsString($decoded);
        $this->assertStringContainsString('<svg', $decoded);
        $this->assertStringContainsString('PDF', $decoded);
    }
}
