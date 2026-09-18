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
use App\Plugins\CiscoPppoe\Support\CustomOidManager;
use App\Plugins\CiscoPppoe\Support\PluginSettings;
use App\Plugins\Hooks\SettingsHook;
use Illuminate\Contracts\Auth\Authenticatable;

class Settings extends SettingsHook
{
    /** Matches the plugin directory name, which is what LibreNMS routes on. */
    private const PLUGIN_NAME = 'CiscoPppoe';

    /**
     * Result of the custom OID creation, kept so it survives the second data() call.
     *
     * SettingsHook::handle() invokes data() twice and returns the second result. The
     * first pass would do the creating and the second would report everything as
     * already present, so the outcome is remembered here instead.
     *
     * @var array<int, array{device: string, state: string, detail: string}>|null
     */
    private ?array $customOidReport = null;

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
        $customOids = new CustomOidManager;

        // The settings form can only post plugin settings, so the button is a link
        // back to this page. The route already requires the plugin.admin ability.
        $this->customOidReport ??= request()->boolean('create_custom_oids')
            ? $customOids->create($matched)
            : [];

        $report = $this->customOidReport;
        $oidStatus = $customOids->status($matched);

        return [
            'custom_oid_report' => $report,
            'custom_oid_description' => CustomOidManager::DESCRIPTION,
            'custom_oid_missing' => count(array_filter($oidStatus, static fn (bool $present): bool => ! $present)),
            'create_custom_oids_url' => url('plugin/settings/' . self::PLUGIN_NAME . '?create_custom_oids=1'),
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
                    'has_custom_oid' => $oidStatus[(int) $device->device_id] ?? false,
                    'graph_url' => url('device/' . $device->device_id . '/graphs/customoid'),
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
