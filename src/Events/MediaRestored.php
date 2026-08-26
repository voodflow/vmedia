<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Voodflow\Vmedia\Models\MediaItem;

class MediaRestored
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public MediaItem $media) {}
}
