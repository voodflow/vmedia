<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Voodflow\Vmedia\Support\RehomeModelMedia;

return new class extends Migration
{
    public function up(): void
    {
        RehomeModelMedia::migrate([
            'Voodflow\\Vevents\\Models\\Event',
            'Voodflow\\Vevents\\Models\\Organizer',
            'Voodflow\\Vevents\\Models\\ScheduleItem',
            'Voodflow\\Vexhibitors\\Models\\Exhibitor',
            'Voodflow\\Vsponsors\\Models\\Sponsor',
            'Voodflow\\Vpartners\\Models\\Partner',
        ]);
    }

    public function down(): void
    {
        // Irreversible: files already live on the vault.
    }
};
