<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Voodflow\Vmedia\Support\UploadGuard;

class UploadGuardTest extends TestCase
{
    public function test_detects_path_traversal_variants(): void
    {
        $this->assertTrue(UploadGuard::containsPathTraversal('../etc/passwd'));
        $this->assertTrue(UploadGuard::containsPathTraversal('..\\windows\\system32'));
        $this->assertTrue(UploadGuard::containsPathTraversal('/etc/passwd'));
        $this->assertTrue(UploadGuard::containsPathTraversal("evil\0.jpg"));
        $this->assertFalse(UploadGuard::containsPathTraversal('photo.jpg'));
        $this->assertFalse(UploadGuard::containsPathTraversal('my-folder/photo.jpg'));
    }

    public function test_assert_safe_relative_path_normalizes(): void
    {
        $this->assertSame(
            '1/file.jpg',
            UploadGuard::assertSafeRelativePath('/1/file.jpg'),
        );
    }

    public function test_assert_safe_relative_path_rejects_traversal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UploadGuard::assertSafeRelativePath('../outside.jpg');
    }
}
