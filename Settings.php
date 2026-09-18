<?php

/*
 * Settings.php
 *
 * Plugin settings: which devices count as a BRAS, how long SNMP results are
 * cached, and whether the expensive per-session walk is allowed.
 */

namespace App\Plugins\CiscoPppoe;

use App\Models\Device;
use App\Plugins\CiscoPppoe\Support\BrasDeviceSelector;
use App\Plugins\CiscoPppoe\Support\PluginSettings;
use App\Plugins\Hooks\SettingsHook;
use Illuminate\Contracts\Auth\Authenticatable;

class Settings extends SettingsHook
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
        return $user->can('plugin.admin');
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
        $matched = $selector->devices();

        return [
            'settings' => $pluginSettings->all(),
            'selection_auto' => PluginSettings::SELECTION_AUTO,
            'selection_manual' => PluginSettings::SELECTION_MANUAL,
            'session_limit_max' => PluginSettings::SESSION_LIMIT_MAX,
            'matched_devices' => $matched->count(),
            // Show what the saved settings actually resolve to, so the operator does
            // not have to open the plugin page to find out whether a filter is right.
            'matched_device_list' => $matched
                ->map(static fn (Device $device): array => [
                    'display' => $device->display ?: $device->hostname,
                    'secondary' => $device->name(),
                    'url' => url('device/' . $device->device_id),
                ])
                ->all(),
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
