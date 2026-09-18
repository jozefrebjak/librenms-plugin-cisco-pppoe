<?php

/*
 * Page.php
 *
 * Standalone page listing every BRAS with its PPPoE session counters, plus a
 * per-interface drill-down for one selected device.
 */

namespace App\Plugins\CiscoPppoe;

use App\Models\Device;
use App\Plugins\CiscoPppoe\Support\BrasDeviceSelector;
use App\Plugins\CiscoPppoe\Support\PluginSettings;
use App\Plugins\CiscoPppoe\Support\PppoeSessionQuery;
use App\Plugins\CiscoPppoe\Support\Presenter;
use App\Plugins\Hooks\PageHook;
use Illuminate\Contracts\Auth\Authenticatable;

class Page extends PageHook
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
        $pluginSettings = PluginSettings::fromArray($settings);
        $selector = new BrasDeviceSelector($pluginSettings);
        $query = new PppoeSessionQuery($pluginSettings);

        $devices = $selector->devices();
        $selectedDevice = $this->selectedDevice($devices, (int) request('device'));

        if ($selectedDevice !== null && request()->boolean('refresh')) {
            $query->forget($selectedDevice);
        }

        $rows = [];
        $totals = ['total' => 0, 'pta' => 0, 'fwded' => 0, 'trans' => 0];

        foreach ($devices as $device) {
            $statistics = Presenter::decorate($query->statistics($device));

            foreach (array_keys($totals) as $counter) {
                $totals[$counter] += (int) $statistics[$counter];
            }

            $rows[] = [
                'device' => $device,
                // Honour the instance wide device_display_default template instead of
                // picking a field ourselves. Device::name() adds whichever of hostname
                // or sysName the display string does not already contain.
                'display' => $device->display ?: $device->hostname,
                'secondary' => $device->name(),
                'statistics' => $statistics,
                'device_url' => url('device/' . $device->device_id),
                'detail_url' => $this->pageUrl(['device' => $device->device_id]),
                'selected' => $selectedDevice !== null && (int) $device->device_id === (int) $selectedDevice->device_id,
            ];
        }

        usort($rows, static fn (array $left, array $right): int => $right['statistics']['total'] <=> $left['statistics']['total']);

        return [
            'title' => 'Cisco PPPoE Sessions',
            'rows' => $rows,
            'totals' => $totals,
            'base_url' => $this->pageUrl(),
            'selected' => $selectedDevice === null ? null : $this->selectedDeviceData($selectedDevice, $query),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function selectedDeviceData(Device $device, PppoeSessionQuery $query): array
    {
        $statistics = Presenter::decorate($query->statistics($device));
        $interfaces = $statistics['interfaces'];

        // The per-interface table covers every ifIndex the BRAS knows about, which on
        // an ASR1000 is mostly sub-interfaces carrying no sessions at all. Hide those
        // by default, they bury the handful of interfaces that matter.
        $showAll = request()->boolean('all');
        $visible = $showAll
            ? $interfaces
            : array_filter($interfaces, static fn (array $interface): bool => $interface['total'] > 0);

        $deviceParameters = ['device' => $device->device_id] + ($showAll ? ['all' => 1] : []);

        return [
            'device' => $device,
            'display' => $device->display ?: $device->hostname,
            'device_url' => url('device/' . $device->device_id),
            'refresh_url' => $this->pageUrl($deviceParameters + ['refresh' => 1]),
            'toggle_interfaces_url' => $this->pageUrl(
                ['device' => $device->device_id] + ($showAll ? [] : ['all' => 1])
            ),
            'statistics' => $statistics,
            'interfaces' => $visible,
            'interface_count' => count($interfaces),
            'hidden_interfaces' => count($interfaces) - count($visible),
            'show_all_interfaces' => $showAll,
            'sessions' => $query->sessions($device),
        ];
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function pageUrl(array $parameters = []): string
    {
        $url = url('plugin/CiscoPppoe');

        return $parameters === [] ? $url : $url . '?' . http_build_query($parameters);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Device>  $devices
     */
    private function selectedDevice(\Illuminate\Support\Collection $devices, int $deviceId): ?Device
    {
        if ($deviceId < 1) {
            return null;
        }

        return $devices->first(static fn (Device $device): bool => (int) $device->device_id === $deviceId);
    }
}
