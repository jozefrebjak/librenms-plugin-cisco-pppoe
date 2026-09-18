<?php

/*
 * PppoeSessionQuery.php
 *
 * Collects PPPoE session data from a Cisco BRAS over SNMP and caches it.
 *
 * A blade view is rendered on every page load, so an uncached query would mean an
 * SNMP round trip to the ASR on every refresh. Everything public here goes through
 * the cache, with the TTL taken from the plugin settings.
 */

namespace App\Plugins\CiscoPppoe\Support;

use App\Models\Device;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SnmpQuery;
use Throwable;

final class PppoeSessionQuery
{
    private const CACHE_PREFIX = 'cisco-pppoe';

    /**
     * Unsigned32 maximum, reported by IOS-XE when no session limit is configured.
     *
     * Seen on ASR1000 running 15.5(3)S: cPppoeSystemMaxAllowedSessions returns
     * 4294967295 rather than the 0 the MIB describes.
     */
    private const NO_LIMIT = 4294967295;

    /**
     * Columns of csubSessionTable the per-session listing walks.
     *
     * Deliberately a subset of Oids::SESSION_COLUMNS: every entry is one more full
     * walk of a table that holds one row per subscriber. csubSessionMacAddress is
     * left out because IOS-XE 15.5(3)S returns an empty string for every session,
     * which is not worth a walk.
     */
    private const WALKED_SESSION_COLUMNS = ['type', 'state', 'username', 'ip'];

    /**
     * Columns walked with forced hex output.
     *
     * Without the MIB loaded net-snmp guesses how to render an OCTET STRING, so an
     * InetAddress whose four bytes all happen to be printable comes back as text
     * rather than hex. Some of those bytes are carriage returns, which do not
     * survive the line based response parser. Forcing hex removes the guesswork.
     *
     * The flags mirror what SnmpQueryOptions::quickPrint() produces, which is what
     * SnmpQuery uses by default, plus -Ox for hex and -On for numeric OIDs.
     */
    private const HEX_SESSION_COLUMNS = ['ip'];

    private const HEX_OUTPUT_FLAGS = '-OQXUtenx';

    public function __construct(private readonly PluginSettings $settings)
    {
    }

    /** @param  array<string, mixed>  $settings */
    public static function fromSettings(array $settings): self
    {
        return new self(PluginSettings::fromArray($settings));
    }

    /**
     * Session counters for one device.
     *
     * Cheap enough to collect on demand: one snmpget plus one walk of the physical
     * interfaces, so a cold cache costs a page load very little.
     *
     * @return array<string, mixed>
     */
    public function statistics(Device $device, bool $live = false): array
    {
        $key = "stats:{$device->device_id}";

        if ($live) {
            return $this->store($key, $this->fetchStatistics($device));
        }

        return $this->remember($key, fn (): array => $this->fetchStatistics($device));
    }

    /**
     * Per-subscriber listing for one device.
     *
     * Read from cache only. This is the one query heavy enough that a page load
     * must never wait for it, so an empty cache reports "not collected" and leaves
     * it to the operator button or the warm-cache script to fill in.
     *
     * @return array<string, mixed>
     */
    public function sessions(Device $device, bool $live = false): array
    {
        if (! $this->settings->sessionWalkEnabled()) {
            return $this->emptySessions(enabled: false);
        }

        $key = "sessions:{$device->device_id}";

        if ($live) {
            return $this->store($key, $this->fetchSessions($device));
        }

        $cached = Cache::get($this->cacheKey($key));

        return is_array($cached) ? $cached : $this->emptySessions(enabled: true, collected: false);
    }

    /**
     * Collect everything for a device and put it in the cache.
     *
     * Used by bin/warm-cache.php so the pages have data waiting for them.
     */
    public function warm(Device $device): void
    {
        $this->statistics($device, live: true);

        if ($this->settings->sessionWalkEnabled()) {
            $this->sessions($device, live: true);
        }
    }

    /**
     * Whether the device answered with usable PPPoE data.
     */
    public function hasData(Device $device): bool
    {
        return (bool) ($this->statistics($device)['available'] ?? false);
    }

    /**
     * Drop the cached data for a device so the next read polls the BRAS again.
     */
    public function forget(Device $device): void
    {
        Cache::forget($this->cacheKey("stats:{$device->device_id}"));
        Cache::forget($this->cacheKey("sessions:{$device->device_id}"));
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchStatistics(Device $device): array
    {
        try {
            $scalars = $this->fetchSystemScalars($device);
            $interfaces = $this->fetchInterfaces($device);
        } catch (Throwable $exception) {
            Log::debug("CiscoPppoe: SNMP query failed for $device->hostname: " . $exception->getMessage());

            return $this->emptyStatistics($exception->getMessage());
        }

        if ($scalars === [] && $interfaces === []) {
            return $this->emptyStatistics('Device returned no CISCO-PPPOE-MIB data');
        }

        $pta = array_sum(array_column($interfaces, 'pta'));
        $fwded = array_sum(array_column($interfaces, 'fwded'));
        $trans = array_sum(array_column($interfaces, 'trans'));
        $interfaceTotal = array_sum(array_column($interfaces, 'total'));

        $systemCurrent = isset($scalars['system_current']) ? SnmpTable::integer($scalars['system_current']) : null;
        $maxAllowed = $this->configuredLimit($scalars['max_allowed'] ?? null);
        $threshold = $this->configuredLimit($scalars['threshold'] ?? null);

        // cPppoeSystemMaxAllowedSessions is the real ceiling, the threshold is only
        // the trap watermark, so fall back to it when no limit is configured.
        $limit = null;
        $limitSource = null;

        if ($maxAllowed !== null) {
            $limit = $maxAllowed;
            $limitSource = 'max';
        } elseif ($threshold !== null) {
            $limit = $threshold;
            $limitSource = 'threshold';
        }

        $total = $systemCurrent ?? $interfaceTotal;

        return [
            'available' => true,
            'error' => null,
            'total' => $total,
            'pta' => $pta,
            'fwded' => $fwded,
            'trans' => $trans,
            'interface_total' => $interfaceTotal,
            'system_current' => $systemCurrent,
            'high_water' => isset($scalars['high_water']) ? SnmpTable::integer($scalars['high_water']) : null,
            'max_allowed' => $maxAllowed,
            'threshold' => $threshold,
            'exceeded_errors' => isset($scalars['exceeded_errors']) ? SnmpTable::integer($scalars['exceeded_errors']) : null,
            'limit' => $limit,
            'limit_source' => $limitSource,
            'utilization' => $limit ? round($total / $limit * 100, 1) : null,
            'interfaces' => $interfaces,
            'polled_at' => time(),
        ];
    }

    /**
     * Read a configured session limit, or null when the BRAS has none.
     *
     * The MIB says an unset limit is 0, but IOS-XE also reports the Unsigned32
     * maximum, which would otherwise render as a 4,294,967,295 session ceiling and
     * flatten every utilisation bar to zero.
     */
    private function configuredLimit(?string $raw): ?int
    {
        if ($raw === null) {
            return null;
        }

        $limit = SnmpTable::integer($raw);

        return ($limit <= 0 || $limit >= self::NO_LIMIT) ? null : $limit;
    }

    /**
     * @return array<string, string>
     */
    private function fetchSystemScalars(Device $device): array
    {
        // hideMib() is not chained here on purpose: it sets the same option as
        // numeric() and would replace numeric OIDs with a bare suffix.
        $response = SnmpQuery::device($device)
            ->numeric()
            ->get(array_keys(Oids::SYSTEM_SCALARS));

        if (! $response->isValid(ignore_partial: true)) {
            return [];
        }

        return SnmpTable::scalars($response->values(), Oids::SYSTEM_SCALARS);
    }

    /**
     * Per physical interface breakdown, keyed by ifIndex.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchInterfaces(Device $device): array
    {
        $response = SnmpQuery::device($device)
            ->numeric()
            ->walk(Oids::PER_INTERFACE_ENTRY);

        if (! $response->isValid(ignore_partial: true)) {
            return [];
        }

        $rows = SnmpTable::rows($response->values(), Oids::PER_INTERFACE_ENTRY);

        if ($rows === []) {
            return [];
        }

        $ports = $this->ports($device, array_keys($rows));
        $interfaces = [];

        foreach ($rows as $ifIndex => $columns) {
            $interface = [
                'ifIndex' => (int) $ifIndex,
                'name' => $ports[(int) $ifIndex]['name'] ?? "ifIndex $ifIndex",
                'alias' => $ports[(int) $ifIndex]['alias'] ?? null,
            ];

            foreach (Oids::PER_INTERFACE_COLUMNS as $column => $key) {
                $interface[$key] = SnmpTable::integer($columns[$column] ?? null);
            }

            $interfaces[(int) $ifIndex] = $interface;
        }

        uasort($interfaces, static fn (array $left, array $right): int => $right['total'] <=> $left['total']);

        return $interfaces;
    }

    /**
     * Interface name and description for the given ifIndexes, keyed by ifIndex.
     *
     * @param  array<int, string>  $ifIndexes
     * @return array<int, array{name: string, alias: string|null}>
     */
    private function ports(Device $device, array $ifIndexes): array
    {
        $ports = [];

        $found = $device->ports()
            ->whereIn('ifIndex', array_map('intval', $ifIndexes))
            ->get(['ifIndex', 'ifName', 'ifDescr', 'ifAlias']);

        foreach ($found as $port) {
            $ports[(int) $port->ifIndex] = [
                'name' => (string) ($port->ifName ?: $port->ifDescr ?: "ifIndex $port->ifIndex"),
                'alias' => trim((string) $port->ifAlias) ?: null,
            ];
        }

        return $ports;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchSessions(Device $device): array
    {
        $columns = [];

        try {
            foreach (self::WALKED_SESSION_COLUMNS as $column) {
                $query = SnmpQuery::device($device);
                $query = in_array($column, self::HEX_SESSION_COLUMNS, true)
                    ? $query->options([self::HEX_OUTPUT_FLAGS])
                    : $query->numeric();

                $response = $query->walk(Oids::SESSION_COLUMNS[$column]);

                if (! $response->isValid(ignore_partial: true)) {
                    continue;
                }

                $columns[$column] = SnmpTable::column($response->values(), Oids::SESSION_COLUMNS[$column]);
            }
        } catch (Throwable $exception) {
            Log::debug("CiscoPppoe: subscriber session walk failed for $device->hostname: " . $exception->getMessage());

            return $this->emptySessions(enabled: true, error: $exception->getMessage());
        }

        if ($columns === []) {
            return $this->emptySessions(enabled: true, error: 'Device returned no CISCO-SUBSCRIBER-SESSION-MIB data');
        }

        $rows = $this->buildSessionRows($columns);
        $limit = $this->settings->sessionLimit();

        return [
            'enabled' => true,
            'collected' => true,
            'available' => true,
            'error' => null,
            'total' => count($rows),
            'limit' => $limit,
            'truncated' => count($rows) > $limit,
            'rows' => array_slice($rows, 0, $limit),
            'polled_at' => time(),
        ];
    }

    /**
     * Merge the walked columns into one row per subscriber session.
     *
     * @param  array<string, array<string, string>>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function buildSessionRows(array $columns): array
    {
        $indexes = [];
        foreach ($columns as $values) {
            foreach (array_keys($values) as $index) {
                $indexes[$index] = true;
            }
        }

        $rows = [];

        foreach (array_keys($indexes) as $index) {
            $type = SnmpTable::integer($columns['type'][$index] ?? null, Oids::SESSION_TYPE_PPPOE);

            // Subscriber sessions cover more than PPPoE, keep only what we advertise.
            if ($type !== Oids::SESSION_TYPE_PPPOE) {
                continue;
            }

            $state = SnmpTable::integer($columns['state'][$index] ?? null);

            $rows[] = [
                'ifIndex' => (int) $index,
                'username' => trim((string) ($columns['username'][$index] ?? '')),
                'state' => Oids::SESSION_STATES[$state] ?? 'unknown',
                'state_value' => $state,
                'ip' => SnmpTable::ipAddress($columns['ip'][$index] ?? null),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => [$left['username'], $left['ifIndex']] <=> [$right['username'], $right['ifIndex']]);

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStatistics(?string $error): array
    {
        return [
            'available' => false,
            'error' => $error,
            'total' => 0,
            'pta' => 0,
            'fwded' => 0,
            'trans' => 0,
            'interface_total' => 0,
            'system_current' => null,
            'high_water' => null,
            'max_allowed' => null,
            'threshold' => null,
            'exceeded_errors' => null,
            'limit' => null,
            'limit_source' => null,
            'utilization' => null,
            'interfaces' => [],
            'polled_at' => time(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySessions(bool $enabled, ?string $error = null, bool $collected = true): array
    {
        return [
            'enabled' => $enabled,
            'collected' => $collected,
            'available' => false,
            'error' => $error,
            'total' => 0,
            'limit' => $this->settings->sessionLimit(),
            'truncated' => false,
            'rows' => [],
            'polled_at' => time(),
        ];
    }

    /**
     * @param  \Closure(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function remember(string $key, \Closure $callback): array
    {
        $ttl = $this->settings->cacheTtl();

        if ($ttl < 1) {
            return $callback();
        }

        return Cache::remember($this->cacheKey($key), $ttl, $callback);
    }

    /**
     * Put a freshly collected result in the cache and hand it back.
     *
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function store(string $key, array $value): array
    {
        $ttl = $this->settings->cacheTtl();

        if ($ttl > 0) {
            Cache::put($this->cacheKey($key), $value, $ttl);
        }

        return $value;
    }

    private function cacheKey(string $key): string
    {
        return self::CACHE_PREFIX . ':' . $key;
    }
}
