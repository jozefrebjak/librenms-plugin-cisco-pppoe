<?php

/*
 * CustomOidManager.php
 *
 * Creates the LibreNMS custom OID that gives the session count a history.
 *
 * The plugin itself stores nothing, so there is no trend to look at. LibreNMS
 * already polls the customoids table on every device (poller_modules.customoid
 * defaults to true) and writes it to RRD, so registering one custom OID per BRAS
 * gets graphing and alerting for free.
 *
 * This only ever adds rows. Existing entries are left exactly as they are, whether
 * the plugin created them or an operator did.
 */

namespace App\Plugins\CiscoPppoe\Support;

use App\Models\Customoid;
use App\Models\Device;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use SnmpQuery;
use Throwable;

final class CustomOidManager
{
    /** Shown in LibreNMS and used for the RRD file name. */
    public const DESCRIPTION = 'PPPoE Sessions';

    private const DATATYPE = 'GAUGE';

    private const UNIT = 'sessions';

    /**
     * Link to the Custom OID section of a device's graphs tab.
     *
     * LibreNMS only turns a path segment into a variable when it contains an "=",
     * see Url::parseLegacyPath(), so "graphs/customoid" selects the tab but leaves
     * the group unset and lands on whichever graph group comes first. This is the
     * legacy form the LibreNMS UI itself generates.
     */
    public static function graphUrl(int $deviceId): string
    {
        return url("device/device=$deviceId/tab=graphs/group=customoid/");
    }

    /**
     * Which of the given devices already have the custom OID.
     *
     * Read only, safe to call while rendering a page.
     *
     * @param  Collection<int, Device>  $devices
     * @return array<int, bool> device_id => present
     */
    public function status(Collection $devices): array
    {
        $deviceIds = $devices->pluck('device_id')->map('intval')->all();

        if ($deviceIds === []) {
            return [];
        }

        $present = Customoid::whereIn('device_id', $deviceIds)
            ->whereIn('customoid_oid', $this->oidVariants())
            ->pluck('device_id')
            ->map('intval')
            ->all();

        $status = [];
        foreach ($deviceIds as $deviceId) {
            $status[$deviceId] = in_array($deviceId, $present, true);
        }

        return $status;
    }

    /**
     * Add the custom OID to every device that does not have it yet.
     *
     * The OID is read from the device first, so a device that does not answer is
     * reported instead of being registered as something the poller will retry
     * forever.
     *
     * @param  Collection<int, Device>  $devices
     * @return array<int, array{device: string, state: string, detail: string}>
     */
    public function create(Collection $devices): array
    {
        $report = [];

        foreach ($devices as $device) {
            $report[] = $this->createForDevice($device);
        }

        return $report;
    }

    /**
     * @return array{device: string, state: string, detail: string}
     */
    private function createForDevice(Device $device): array
    {
        $name = $device->display ?: $device->hostname;

        if ($this->existingFor($device) !== null) {
            return ['device' => $name, 'state' => 'exists', 'detail' => 'Already present, left untouched'];
        }

        try {
            $response = SnmpQuery::device($device)
                ->numeric()
                ->get(Oids::SYSTEM_CURRENT_SESSIONS);

            $value = $response->isValid() ? SnmpTable::integer($response->value()) : null;
        } catch (Throwable $exception) {
            Log::debug("CiscoPppoe: custom OID check failed for $device->hostname: " . $exception->getMessage());

            return ['device' => $name, 'state' => 'failed', 'detail' => $exception->getMessage()];
        }

        if ($value === null) {
            return [
                'device' => $name,
                'state' => 'failed',
                'detail' => 'Device did not answer cPppoeSystemCurrSessions',
            ];
        }

        Customoid::create([
            'device_id' => $device->device_id,
            'customoid_descr' => self::DESCRIPTION,
            'customoid_oid' => Oids::SYSTEM_CURRENT_SESSIONS,
            'customoid_datatype' => self::DATATYPE,
            'customoid_unit' => self::UNIT,
            // The poller skips rows that have not passed a check, and we just read
            // the value ourselves, so it has passed.
            'customoid_passed' => 1,
        ]);

        return [
            'device' => $name,
            'state' => 'created',
            'detail' => 'Answered with ' . number_format($value) . ' sessions',
        ];
    }

    private function existingFor(Device $device): ?Customoid
    {
        return Customoid::where('device_id', $device->device_id)
            ->whereIn('customoid_oid', $this->oidVariants())
            ->first();
    }

    /**
     * The same OID with and without the leading dot, because a hand created entry
     * may have been typed either way.
     *
     * @return string[]
     */
    private function oidVariants(): array
    {
        return [
            Oids::SYSTEM_CURRENT_SESSIONS,
            ltrim(Oids::SYSTEM_CURRENT_SESSIONS, '.'),
        ];
    }
}
