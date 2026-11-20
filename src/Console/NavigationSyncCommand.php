<?php

namespace Iquesters\Foundation\Console;

use Illuminate\Console\Command;
use Iquesters\Foundation\Services\Navigation\NavigationSync;

/** Copies the modules' declared menus into navigation_items (admin edits are kept). */
class NavigationSyncCommand extends Command
{
    protected $signature = 'navigation:sync';

    protected $description = 'Sync the modules\' sidebar items and tabs into navigation_items (admin edits are kept)';

    public function handle(NavigationSync $sync): int
    {
        if (! $sync->available()) {
            $this->error('Run the migrations first (navigation_items is missing).');

            return self::FAILURE;
        }

        $sync->syncAll();
        $this->info('Navigation synced.');

        return self::SUCCESS;
    }
}
