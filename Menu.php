<?php

/*
 * Menu.php
 *
 * Plugin menu entry pointing at the PPPoE overview page.
 */

namespace App\Plugins\CiscoPppoe;

use App\Plugins\CiscoPppoe\Support\BrasDeviceSelector;
use App\Plugins\CiscoPppoe\Support\PluginSettings;
use App\Plugins\Hooks\MenuEntryHook;
use Illuminate\Contracts\Auth\Authenticatable;

class Menu extends MenuEntryHook
{
    /**
     * Type hinted as Authenticatable, not as App\Models\User.
     *
     * LibreNMS resolves hook arguments through the service container, and the User
     * model is not bound there, so a User type hint would hand us a fresh empty
     * model whose can() always returns false. Authenticatable is bound to the
     * logged in user.
     */
    public function authorize(Authenticatable $user): bool
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
