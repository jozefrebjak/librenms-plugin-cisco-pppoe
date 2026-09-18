<?php

/*
 * BrasDeviceSelector.php
 *
 * Decides which LibreNMS devices the plugin treats as PPPoE BRAS.
 */

namespace App\Plugins\CiscoPppoe\Support;

use App\Models\Device;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class BrasDeviceSelector
{
    public function __construct(private readonly PluginSettings $settings)
    {
    }

    /**
     * Devices the plugin should show, ordered by display name.
     *
     * @return Collection<int, Device>
     */
    public function devices(): Collection
    {
        return $this->query()->orderBy('hostname')->get();
    }

    /**
     * How many devices the plugin considers a BRAS.
     *
     * Used by the menu entry, which is rendered on every page, so it counts in SQL
     * instead of hydrating models.
     */
    public function count(): int
    {
        return $this->query()->count();
    }

    /**
     * Whether a single device belongs to the configured BRAS set.
     *
     * Evaluated in PHP so the device overview hook does not need a database
     * round trip for every device page.
     */
    public function includes(Device $device): bool
    {
        if ($device->disabled) {
            return false;
        }

        if ($this->settings->selectsManually()) {
            return in_array((int) $device->device_id, $this->settings->deviceIds(), true);
        }

        $osFilter = $this->settings->osFilter();

        if ($osFilter !== [] && ! in_array((string) $device->os, $osFilter, true)) {
            return false;
        }

        $sysNameFilter = $this->settings->sysNameFilter();

        if ($sysNameFilter === '') {
            return true;
        }

        return Str::contains(strtolower((string) $device->sysName), $sysNameFilter)
            || Str::contains(strtolower((string) $device->hostname), $sysNameFilter);
    }

    /**
     * Devices that may be picked on the settings page.
     *
     * Restricted to the configured OS list so the manual picker stays usable on
     * an installation with thousands of devices.
     *
     * @return Collection<int, Device>
     */
    public function selectableDevices(): Collection
    {
        $query = Device::query()->where('disabled', 0);
        $osFilter = $this->settings->osFilter();

        if ($osFilter !== []) {
            $query->whereIn('os', $osFilter);
        }

        return $query->orderBy('hostname')->get();
    }

    private function query(): Builder
    {
        $query = Device::query()->where('disabled', 0);

        if ($this->settings->selectsManually()) {
            return $query->whereIn('device_id', $this->settings->deviceIds() ?: [0]);
        }

        $osFilter = $this->settings->osFilter();

        if ($osFilter !== []) {
            $query->whereIn('os', $osFilter);
        }

        $sysNameFilter = $this->settings->sysNameFilter();

        if ($sysNameFilter !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $sysNameFilter) . '%';

            $query->where(function (Builder $nameQuery) use ($like): void {
                $nameQuery->where('sysName', 'like', $like)
                    ->orWhere('hostname', 'like', $like);
            });
        }

        return $query;
    }
}
