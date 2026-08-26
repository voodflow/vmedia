<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Voodflow\Vmedia\Models\MediaItem;

class MediaDetached
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Model $attachable,
        public MediaItem $media,
        public string $collection,
    ) {}
}
