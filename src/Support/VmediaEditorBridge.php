<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Support;

use Illuminate\Support\Facades\Schema;
use Voodflow\Vmedia\Models\MediaGallery;
use Voodflow\Voodbuilder\Voodbuilder;

/**
 * Exposes gallery options + labels to the VoodBuilder editor settings panel.
 */
final class VmediaEditorBridge
{
    public static function register(): void
    {
        if (! class_exists(Voodbuilder::class)) {
            return;
        }

        Voodbuilder::editorConfig(static fn (): array => [
            'vmedia' => [
                'galleries' => self::galleryOptions(),
                'captionColors' => CaptionBackground::editorOptions(),
            ],
        ]);

        Voodbuilder::editorLabels(static fn (): array => [
            'vmediaSettingsTitle' => __('vmedia::admin.editor.settings_title'),
            'vmediaGallery' => __('vmedia::admin.editor.gallery'),
            'vmediaNoGallery' => __('vmedia::admin.editor.no_gallery'),
            'vmediaColumns' => __('vmedia::admin.editor.columns'),
            'vmediaLimit' => __('vmedia::admin.editor.limit'),
            'vmediaGap' => __('vmedia::admin.editor.gap'),
            'vmediaAspect' => __('vmedia::admin.editor.aspect'),
            'vmediaShowCaptions' => __('vmedia::admin.editor.show_captions'),
            'vmediaCaptionPosition' => __('vmedia::admin.editor.caption_position'),
            'vmediaCaptionBelow' => __('vmedia::admin.editor.caption_below'),
            'vmediaCaptionAbove' => __('vmedia::admin.editor.caption_above'),
            'vmediaCaptionOverlay' => __('vmedia::admin.editor.caption_overlay'),
            'vmediaCaptionBg' => __('vmedia::admin.editor.caption_bg'),
            'vmediaCaptionBgOpacity' => __('vmedia::admin.editor.caption_bg_opacity'),
            'vmediaCredits' => __('vmedia::admin.editor.credits'),
            'vmediaLightbox' => __('vmedia::admin.editor.lightbox'),
            'vmediaSpeed' => __('vmedia::admin.editor.speed'),
            'vmediaPauseOnHover' => __('vmedia::admin.editor.pause_on_hover'),
            'vmediaDirection' => __('vmedia::admin.editor.direction'),
            'vmediaDirectionLeft' => __('vmedia::admin.editor.direction_left'),
            'vmediaDirectionRight' => __('vmedia::admin.editor.direction_right'),
            'vmediaRounded' => __('vmedia::admin.editor.rounded'),
            'vmediaHeading' => __('vmedia::admin.editor.heading'),
            'vmediaGapSm' => __('vmedia::admin.editor.gap_sm'),
            'vmediaGapMd' => __('vmedia::admin.editor.gap_md'),
            'vmediaGapLg' => __('vmedia::admin.editor.gap_lg'),
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function galleryOptions(): array
    {
        if (! class_exists(MediaGallery::class)) {
            return [];
        }

        $table = (new MediaGallery)->getTable();

        if (! Schema::hasTable($table)) {
            return [];
        }

        $options = GalleryDisplay::albumSelectOptions();
        $items = [];

        foreach ($options as $id => $label) {
            $items[] = [
                'value' => (string) $id,
                'label' => $label,
            ];
        }

        return $items;
    }
}
