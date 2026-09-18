<?php

/*
 * Menu.php
 *
 * Plugin menu entry pointing at the PPPoE overview page.
 */

namespace App\Plugins\CiscoPppoe;

use App\Models\User;
use App\Plugins\CiscoPppoe\Support\BrasDeviceSelector;
use App\Plugins\CiscoPppoe\Support\PluginSettings;
use App\Plugins\Hooks\MenuEntryHook;

class Menu extends MenuEntryHook
{
    public function authorize(User $user): bool
    {
        return $user->can('global-read');
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function data(array $settings = []): array
    {
        $selector = new BrasDeviceSelector(PluginSettings::fromArray($settings));

        return [
            'url' => url('plugin/CiscoPppoe'),
            'count' => $selector->count(),
        ];
    }
}
