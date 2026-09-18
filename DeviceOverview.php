<?php

/*
 * DeviceOverview.php
 *
 * PPPoE session panel on the device overview page.
 */

namespace App\Plugins\CiscoPppoe;

use App\Models\Device;
use App\Models\User;
use App\Plugins\CiscoPppoe\Support\BrasDeviceSelector;
use App\Plugins\CiscoPppoe\Support\PluginSettings;
use App\Plugins\CiscoPppoe\Support\PppoeSessionQuery;
use App\Plugins\CiscoPppoe\Support\Presenter;
use App\Plugins\Hooks\DeviceOverviewHook;

class DeviceOverview extends DeviceOverviewHook
{
    /**
     * Only show the panel on devices that are a configured BRAS and actually
     * answer with PPPoE data, so it does not appear on every switch.
     *
     * @param  array<string, mixed>  $settings
     */
    public function authorize(User $user, Device $device, array $settings = []): bool
    {
        $pluginSettings = PluginSettings::fromArray($settings);

        if (! (new BrasDeviceSelector($pluginSettings))->includes($device)) {
            return false;
        }

        return (new PppoeSessionQuery($pluginSettings))->hasData($device);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function data(Device $device, array $settings = []): array
    {
        $statistics = PppoeSessionQuery::fromSettings($settings)->statistics($device);

        return [
            'title' => 'PPPoE Sessions',
            'device' => $device,
            'statistics' => Presenter::decorate($statistics),
            'url' => url('plugin/CiscoPppoe?device=' . $device->device_id),
        ];
    }
}
