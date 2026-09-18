<?php

/*
 * SnmpTable.php
 *
 * Turns raw net-snmp output into plain PHP values.
 *
 * Everything here is pure: it takes the array returned by SnmpResponse::values()
 * and needs no device, no network and no SNMP agent, so it can be exercised in
 * isolation with captured walk output.
 */

namespace App\Plugins\CiscoPppoe\Support;

final class SnmpTable
{
    /**
     * Split a numeric walk of a table entry into rows.
     *
     * The walk is expected to cover a whole conceptual row entry, so each returned
     * OID looks like "<entryOid>.<column>.<index>". Indexes made of several
     * sub-identifiers are preserved as a dotted string.
     *
     * @param  array<string, string>  $values  output of SnmpResponse::values()
     * @param  string  $entryOid  numeric OID of the table entry
     * @return array<string, array<int, string>> index => [column number => value]
     */
    public static function rows(array $values, string $entryOid): array
    {
        $base = trim($entryOid, '.');
        $rows = [];

        foreach ($values as $oid => $value) {
            $suffix = self::suffix((string) $oid, $base);

            if ($suffix === null) {
                continue;
            }

            $parts = explode('.', $suffix);
            $column = (int) array_shift($parts);

            if ($column === 0 || $parts === []) {
                continue;
            }

            $rows[implode('.', $parts)][$column] = (string) $value;
        }

        return $rows;
    }

    /**
     * Split a numeric walk of a single columnar object into index => value pairs.
     *
     * @param  array<string, string>  $values  output of SnmpResponse::values()
     * @param  string  $columnOid  numeric OID of the columnar object
     * @return array<string, string>
     */
    public static function column(array $values, string $columnOid): array
    {
        $base = trim($columnOid, '.');
        $column = [];

        foreach ($values as $oid => $value) {
            $suffix = self::suffix((string) $oid, $base);

            if ($suffix === null || $suffix === '') {
                continue;
            }

            $column[$suffix] = (string) $value;
        }

        return $column;
    }

    /**
     * Pick the requested scalars out of a response, ignoring the leading dot.
     *
     * @param  array<string, string>  $values  output of SnmpResponse::values()
     * @param  array<string, string>  $oidMap  scalar OID => key to return it under
     * @return array<string, string>
     */
    public static function scalars(array $values, array $oidMap): array
    {
        $normalised = [];
        foreach ($values as $oid => $value) {
            $normalised[trim((string) $oid, '.')] = (string) $value;
        }

        $scalars = [];
        foreach ($oidMap as $oid => $key) {
            $lookup = trim($oid, '.');

            if (isset($normalised[$lookup])) {
                $scalars[$key] = $normalised[$lookup];
            }
        }

        return $scalars;
    }

    /**
     * Read a counter/gauge value as an integer.
     *
     * LibreNMS asks net-snmp for quick output, so values normally arrive as bare
     * numbers. Tolerate a type prefix ("Gauge32: 42") or a unit suffix
     * ("42 sessions") anyway, because those depend on which MIBs the poller loaded.
     */
    public static function integer(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return $default;
        }

        // Drop a net-snmp type prefix before looking for digits, otherwise the "32"
        // in "Gauge32: 42" would win.
        $value = (string) preg_replace('/^[A-Za-z][A-Za-z0-9-]*:\s*/', '', trim($value));

        if (! preg_match('/-?\d+/', $value, $matches)) {
            return $default;
        }

        return (int) $matches[0];
    }

    /**
     * Format a MacAddress octet string as aa:bb:cc:dd:ee:ff.
     */
    public static function macAddress(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        // When the MacAddress display hint is applied, leading zeros are dropped
        // ("0:1a:2b:3c:4d:5e"), so pad the groups back instead of counting characters.
        if (preg_match('/^[0-9a-fA-F]{1,2}([:-])(?:[0-9a-fA-F]{1,2}\1){4}[0-9a-fA-F]{1,2}$/', $value)) {
            $groups = preg_split('/[:-]/', $value) ?: [];

            return strtolower(implode(':', array_map(
                static fn (string $group): string => str_pad($group, 2, '0', STR_PAD_LEFT),
                $groups
            )));
        }

        $hex = strtolower((string) preg_replace('/[^0-9a-fA-F]/', '', $value));

        if (strlen($hex) !== 12) {
            return null;
        }

        return implode(':', str_split($hex, 2));
    }

    /**
     * Format an InetAddress octet string as a printable IP address.
     *
     * Without the MIB loaded net-snmp cannot apply the display hint, so the same
     * address may arrive as a hex string, as an already printable address, or as
     * raw bytes when every byte happens to be printable ASCII.
     */
    public static function ipAddress(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
            return $value;
        }

        $hex = (string) preg_replace('/[^0-9a-fA-F]/', '', $value);
        $looksHex = $hex !== '' && preg_match('/^(?:[0-9a-fA-F]{2}[\s:.-]*)+$/', $value) === 1;

        if ($looksHex && in_array(strlen($hex), [8, 32], true)) {
            $binary = (string) hex2bin($hex);

            return self::binaryToIp($binary);
        }

        return self::binaryToIp($value);
    }

    /**
     * Read a TimeStamp (TimeTicks, hundredths of a second) as whole seconds.
     */
    public static function timeTicksToSeconds(?string $value): ?int
    {
        if ($value === null || ! preg_match('/\d+/', $value, $matches)) {
            return null;
        }

        return intdiv((int) $matches[0], 100);
    }

    private static function binaryToIp(string $binary): ?string
    {
        if (! in_array(strlen($binary), [4, 16], true)) {
            return null;
        }

        $address = @inet_ntop($binary);

        return $address === false ? null : $address;
    }

    /**
     * Return everything after "$base." in $oid, or null when $oid is not below $base.
     */
    private static function suffix(string $oid, string $base): ?string
    {
        $oid = trim($oid, '.');

        if ($oid === $base) {
            return '';
        }

        if (! str_starts_with($oid, $base . '.')) {
            return null;
        }

        return substr($oid, strlen($base) + 1);
    }
}
