<?php

/*
 * Settings.php
 *
 * Plugin settings: which devices count as a BRAS, how long SNMP results are
 * cached, and whether the expensive per-session walk is allowed.
 */

namespace App\Plugins\CiscoPppoe;

use App\Models\Device;
use App\Models\User;
use App\Plugins\CiscoPppoe\Support\BrasDeviceSelector;
use App\Plugins\CiscoPppoe\Support\PluginSettings;
use App\Plugins\Hooks\SettingsHook;

class Settings extends SettingsHook
{
    public function authorize(User $user): bool
    {
        return $user->can('admin');
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function data(array $settings = []): array
    {
        // PluginSettings::fromArray() unwraps the result of the previous call,
        // because SettingsHook::handle() invokes data() twice.
        $pluginSettings = PluginSettings::fromArray($settings);
        $selector = new BrasDeviceSelector($pluginSettings);

        return [
            'settings' => $pluginSettings->all(),
            'selection_auto' => PluginSettings::SELECTION_AUTO,
            'selection_manual' => PluginSettings::SELECTION_MANUAL,
            'session_limit_max' => PluginSettings::SESSION_LIMIT_MAX,
            'matched_devices' => $selector->count(),
            'selectable_devices' => $selector->selectableDevices()
                ->map(static fn (Device $device): array => [
                    'device_id' => (int) $device->device_id,
                    'label' => $device->hostname . ($device->sysName && $device->sysName !== $device->hostname
                        ? ' (' . $device->sysName . ')'
                        : ''),
                    'os' => (string) $device->os,
                ])
                ->all(),
        ];
    }
}
