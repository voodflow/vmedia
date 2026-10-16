<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Vmedia\Models\MediaItem;
use Voodflow\Vmedia\Support\MediaExplorer;
use Voodflow\Vmedia\Support\MediaLibrary;
use Voodflow\Vmedia\Tests\TestCase;

/**
 * Public-surface query behind the "Media explorer" Livewire block.
 *
 * The important property is the boundary: anonymous visitors browse this through a
 * public endpoint, so media that only lives in a private album must never appear —
 * not in the list, the search, a type tab, the counts or the album filter.
 */
final class MediaExplorerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The fake image bytes are identical across calls, so the duplicate detector
        // would collapse the fixtures into one vault row and hide the filter behaviour
        // these tests are about.
        config()->set('vmedia.duplicates.detect', false);
        config()->set('vmedia.duplicates.reuse', false);
    }

    public function test_it_never_exposes_media_that_only_lives_in_a_private_album(): void
    {
        $public = $this->album('Public album', isPublic: true);
        $private = $this->album('Private album', isPublic: false);

        $visible = $this->image('visible', $public);
        $this->image('hidden', $private);

        $result = MediaExplorer::paginate();

        $this->assertSame(1, $result['meta']['total']);
        $this->assertSame([(int) $visible->getKey()], array_column($result['data'], 'id'));
    }

    public function test_media_shared_between_a_public_and_a_private_album_is_visible(): void
    {
        $public = $this->album('Public album', isPublic: true);
        $private = $this->album('Private album', isPublic: false);

        $this->image('shared', [$public, $private]);

        $result = MediaExplorer::paginate();

        $this->assertSame(1, $result['meta']['total']);
        // The payload only reports the public membership.
        $this->assertSame([(int) $public->getKey()], $result['data'][0]['gallery_ids']);
    }

    public function test_it_searches_name_file_name_caption_and_alt(): void
    {
        $album = $this->album('Library', isPublic: true);

        $named = $this->image('arduino-board', $album);
        $captioned = $this->image('sunset', $album);
        $captioned->setCaption('Pure Data patch')->save();
        $alt = $this->image('photo', $album);
        $alt->setAlt('Granular synthesis rig')->save();

        $this->assertSame(
            [(int) $named->getKey()],
            array_column(MediaExplorer::paginate(search: 'arduino')['data'], 'id'),
        );
        $this->assertSame(
            [(int) $captioned->getKey()],
            array_column(MediaExplorer::paginate(search: 'data patch')['data'], 'id'),
        );
        $this->assertSame(
            [(int) $alt->getKey()],
            array_column(MediaExplorer::paginate(search: 'synthesis')['data'], 'id'),
        );
        $this->assertSame(0, MediaExplorer::paginate(search: 'nothing-matches')['meta']['total']);
    }

    public function test_it_filters_by_type(): void
    {
        $album = $this->album('Library', isPublic: true);

        $this->image('photo', $album);
        $this->video('clip', $album);
        $this->document('spec', $album);

        $this->assertSame(3, MediaExplorer::paginate()['meta']['total']);
        $this->assertSame(1, MediaExplorer::paginate(type: MediaExplorer::TYPE_IMAGE)['meta']['total']);
        $this->assertSame(1, MediaExplorer::paginate(type: MediaExplorer::TYPE_VIDEO)['meta']['total']);
        $this->assertSame(1, MediaExplorer::paginate(type: MediaExplorer::TYPE_FILE)['meta']['total']);

        // An unknown type is never a filter the visitor can smuggle in.
        $this->assertSame(3, MediaExplorer::paginate(type: 'audio')['meta']['total']);
    }

    public function test_it_filters_by_public_album_and_refuses_a_private_one(): void
    {
        $alpha = $this->album('Alpha', isPublic: true);
        $beta = $this->album('Beta', isPublic: true);
        $private = $this->album('Private album', isPublic: false);

        $this->image('in-alpha', $alpha);
        $this->image('also-alpha', $alpha);
        $this->image('in-beta', $beta);
        $this->image('in-private', $private);

        $alphaResult = MediaExplorer::paginate(albumId: (int) $alpha->getKey());

        $this->assertSame(2, $alphaResult['meta']['total']);
        $this->assertSame([(int) $alpha->getKey()], $alphaResult['data'][0]['gallery_ids']);

        $this->assertSame(1, MediaExplorer::paginate(albumId: (int) $beta->getKey())['meta']['total']);
        $this->assertSame(0, MediaExplorer::paginate(albumId: (int) $private->getKey())['meta']['total']);
        $this->assertSame(0, MediaExplorer::paginate(albumId: 999_999)['meta']['total']);
    }

    public function test_it_paginates_and_reports_has_more(): void
    {
        $album = $this->album('Library', isPublic: true);

        for ($i = 1; $i <= 5; $i++) {
            $this->image('photo-' . $i, $album);
        }

        $first = MediaExplorer::paginate(perPage: 2);

        $this->assertCount(2, $first['data']);
        $this->assertSame(5, $first['meta']['total']);
        $this->assertSame(3, $first['meta']['last_page']);
        $this->assertTrue($first['meta']['has_more']);

        $last = MediaExplorer::paginate(page: 3, perPage: 2);

        $this->assertCount(1, $last['data']);
        $this->assertFalse($last['meta']['has_more']);
    }

    public function test_type_counts_reflect_search_and_album_scope(): void
    {
        $alpha = $this->album('Alpha', isPublic: true);
        $beta = $this->album('Beta', isPublic: true);

        $this->image('arduino photo', $alpha);
        $this->video('arduino clip', $alpha);
        $this->document('arduino spec', $alpha);
        $this->image('arduino other', $beta);

        $counts = MediaExplorer::typeCounts();

        $this->assertSame(2, $counts[MediaExplorer::TYPE_IMAGE]);
        $this->assertSame(1, $counts[MediaExplorer::TYPE_VIDEO]);
        $this->assertSame(1, $counts[MediaExplorer::TYPE_FILE]);
        $this->assertSame(4, $counts['total']);

        $scoped = MediaExplorer::typeCounts(albumId: (int) $alpha->getKey());

        $this->assertSame(1, $scoped[MediaExplorer::TYPE_IMAGE]);
        $this->assertSame(3, $scoped['total']);
    }

    public function test_album_options_only_list_public_albums_that_hold_media(): void
    {
        $filled = $this->album('Filled', isPublic: true);
        $empty = $this->album('Empty', isPublic: true);
        $private = $this->album('Private album', isPublic: false);

        $this->image('photo', $filled);
        $this->image('private photo', $private);

        $options = MediaExplorer::albumOptions();

        $this->assertSame([(int) $filled->getKey()], array_column($options, 'id'));
        $this->assertSame('Filled', $options[0]['name']);
        $this->assertSame(1, $options[0]['count']);
        $this->assertNotContains((int) $empty->getKey(), array_column($options, 'id'));
    }

    public function test_normalizers_clamp_public_input(): void
    {
        $this->assertNull(MediaExplorer::normalizeType(''));
        $this->assertNull(MediaExplorer::normalizeType('audio'));
        $this->assertSame('image', MediaExplorer::normalizeType('IMAGE'));
        $this->assertSame(1, MediaExplorer::normalizePerPage(0));
        $this->assertSame(48, MediaExplorer::normalizePerPage(5000));
        $this->assertSame(480, MediaExplorer::normalizeWindow(99_999, 12));
        $this->assertSame(12, MediaExplorer::normalizeWindow(0, 12));
        $this->assertNull(MediaExplorer::normalizeAlbumId(''));
        $this->assertNull(MediaExplorer::normalizeAlbumId('abc'));
        $this->assertNull(MediaExplorer::normalizeAlbumId(0));
        $this->assertSame(7, MediaExplorer::normalizeAlbumId('7'));
    }

    public function test_credit_line_prefers_credits_then_caption_and_never_invents_text(): void
    {
        $this->assertSame(
            'Photo by Jane Doe on Pixabay',
            MediaExplorer::creditSource(['caption' => ' Photo by Jane Doe on Pixabay ']),
        );

        $this->assertSame(
            'Photo by Jane Doe on Pixabay',
            MediaExplorer::creditSource([
                'credits' => 'Photo by Jane Doe on Pixabay',
                'caption' => 'Another caption',
            ]),
        );

        // Empty caption / credits (or none at all) means no credit line, not a default.
        $this->assertSame('', MediaExplorer::creditSource(['caption' => '   ', 'credits' => null]));
        $this->assertSame('', MediaExplorer::creditSource([]));
        $this->assertSame('', MediaExplorer::creditHtml(['caption' => '']));

        // Plain text is escaped, http(s) links survive, anything else is dropped.
        $this->assertSame('A &amp; B', MediaExplorer::creditHtml(['caption' => 'A & B']));

        $html = MediaExplorer::creditHtml([
            'caption' => 'Photo by <a href="https://pixabay.com/photos/1/" onclick="x()">Jane</a>',
        ]);

        $this->assertStringContainsString('href="https://pixabay.com/photos/1/"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringNotContainsString('onclick', $html);
    }

    private function album(string $name, bool $isPublic): MediaGallery
    {
        return MediaGallery::query()->create([
            'name' => $name,
            'kind' => MediaGallery::KIND_ALBUM,
            'is_default' => false,
            'is_public' => $isPublic,
            'sort_order' => 5,
            'parent_key' => 0,
        ]);
    }

    /**
     * @param  MediaGallery|list<MediaGallery>  $albums
     */
    private function image(string $name, MediaGallery | array $albums): MediaItem
    {
        Storage::fake('public');

        return MediaLibrary::store(
            UploadedFile::fake()->image($name . '.jpg', 16, 16),
            $albums,
            $name,
        );
    }

    private function video(string $name, MediaGallery $album): MediaItem
    {
        Storage::fake('public');

        return MediaLibrary::store(
            UploadedFile::fake()->create($name . '.mp4', 8, 'video/mp4'),
            $album,
            $name,
        );
    }

    private function document(string $name, MediaGallery $album): MediaItem
    {
        Storage::fake('public');

        return MediaLibrary::store(
            UploadedFile::fake()->create($name . '.pdf', 4, 'application/pdf'),
            $album,
            $name,
        );
    }
}
