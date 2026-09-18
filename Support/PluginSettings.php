<?php

/*
 * PluginSettings.php
 *
 * Typed access to the values stored by the plugin settings page.
 */

namespace App\Plugins\CiscoPppoe\Support;

final class PluginSettings
{
    /** Pick BRAS devices automatically from their OS and sysName. */
    public const SELECTION_AUTO = 'auto';

    /** Use the device list the operator ticked on the settings page. */
    public const SELECTION_MANUAL = 'manual';

    /** Hard ceiling for the per-session listing, whatever the operator configures. */
    public const SESSION_LIMIT_MAX = 20000;

    /** @var array<string, mixed> */
    public const DEFAULTS = [
        'device_selection' => self::SELECTION_AUTO,
        'os_filter' => 'iosxe',
        'sysname_filter' => 'bras',
        'device_ids' => [],
        'cache_ttl' => 60,
        'session_walk' => 0,
        'session_limit' => 500,
    ];

    /** @param  array<string, mixed>  $values */
    private function __construct(private readonly array $values)
    {
    }

    /** @param  array<string, mixed>  $settings */
    public static function fromArray(array $settings): self
    {
        return new self(array_merge(self::DEFAULTS, self::unwrap($settings)));
    }

    /**
     * Undo the double invocation of SettingsHook::data().
     *
     * SettingsHook::handle() calls data() twice, feeding the first result back in
     * as the $settings argument, so data() may receive its own return value
     * instead of the stored settings. Unwrapping keeps both invocations honest.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function unwrap(array $settings): array
    {
        if (isset($settings['settings']) && is_array($settings['settings'])) {
            return $settings['settings'];
        }

        return $settings;
    }

    /**
     * Every setting merged over the defaults, for rendering the settings form.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return [
            'device_selection' => $this->deviceSelection(),
            'os_filter' => implode(',', $this->osFilter()),
            'sysname_filter' => $this->sysNameFilter(),
            'device_ids' => $this->deviceIds(),
            'cache_ttl' => $this->cacheTtl(),
            'session_walk' => $this->sessionWalkEnabled() ? 1 : 0,
            'session_limit' => $this->sessionLimit(),
        ];
    }

    public function deviceSelection(): string
    {
        return $this->values['device_selection'] === self::SELECTION_MANUAL
            ? self::SELECTION_MANUAL
            : self::SELECTION_AUTO;
    }

    public function selectsManually(): bool
    {
        return $this->deviceSelection() === self::SELECTION_MANUAL;
    }

    /**
     * LibreNMS OS names a BRAS may run, empty means "any OS".
     *
     * @return string[]
     */
    public function osFilter(): array
    {
        return $this->splitList($this->values['os_filter'] ?? '');
    }

    /**
     * Substring a device sysName must contain to count as a BRAS.
     *
     * LibreNMS stores sysName lowercased, so the filter is lowercased too.
     */
    public function sysNameFilter(): string
    {
        return strtolower(trim((string) ($this->values['sysname_filter'] ?? '')));
    }

    /** @return int[] */
    public function deviceIds(): array
    {
        $ids = $this->values['device_ids'] ?? [];

        if (is_string($ids)) {
            $ids = $this->splitList($ids);
        }

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $ids)));
    }

    /** Seconds to keep SNMP results, 0 disables caching. */
    public function cacheTtl(): int
    {
        return max(0, (int) ($this->values['cache_ttl'] ?? 0));
    }

    /**
     * Whether to walk csubSessionTable for the per-session listing.
     *
     * Off by default: on a BRAS with tens of thousands of subscribers the walk is
     * slow enough to hurt, so the operator has to ask for it.
     */
    public function sessionWalkEnabled(): bool
    {
        return (bool) ($this->values['session_walk'] ?? false);
    }

    /** How many sessions the per-session listing may show. */
    public function sessionLimit(): int
    {
        $limit = (int) ($this->values['session_limit'] ?? 0);

        if ($limit < 1) {
            $limit = (int) self::DEFAULTS['session_limit'];
        }

        return min($limit, self::SESSION_LIMIT_MAX);
    }

    /** @return string[] */
    private function splitList(mixed $value): array
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $parts = preg_split('/[\s,]+/', (string) $value) ?: [];
        }

        return array_values(array_filter(array_map(
            static fn ($part): string => trim((string) $part),
            $parts
        ), static fn (string $part): bool => $part !== ''));
    }
}
