#!/usr/bin/env php
<?php

/*
 * warm-cache.php
 *
 * Collects PPPoE data for every configured BRAS and puts it in the cache, so the
 * plugin pages have data waiting instead of polling while a page renders.
 *
 * LibreNMS v2 plugin hooks are UI only, there is no poller hook a local plugin can
 * implement, so this runs from cron outside the request cycle. Run it as the
 * librenms user, inside the container, at an interval shorter than the plugin's
 * cache TTL:
 *
 *   docker compose exec -T -u librenms librenms \
 *     php /opt/librenms/app/Plugins/CiscoPppoe/bin/warm-cache.php
 *
 * See README.md for the exact cron line.
 */

use App\Models\Plugin;
use App\Plugins\CiscoPppoe\Support\BrasDeviceSelector;
use App\Plugins\CiscoPppoe\Support\PluginSettings;
use App\Plugins\CiscoPppoe\Support\PppoeSessionQuery;
use Illuminate\Contracts\Console\Kernel;

const PLUGIN_NAME = 'CiscoPppoe';

// .../app/Plugins/CiscoPppoe/bin -> LibreNMS install root
$installPath = dirname(__DIR__, 4);

if (! is_file($installPath . '/vendor/autoload.php')) {
    fwrite(STDERR, "Could not find a LibreNMS install at $installPath\n");
    exit(1);
}

require $installPath . '/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require $installPath . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$plugin = Plugin::where('plugin_name', PLUGIN_NAME)->first();

if ($plugin === null) {
    fwrite(STDERR, PLUGIN_NAME . " is not installed\n");
    exit(1);
}

if (! $plugin->plugin_active) {
    fwrite(STDERR, PLUGIN_NAME . " is disabled, nothing to collect\n");
    exit(0);
}

$settings = PluginSettings::fromArray((array) $plugin->settings);
$devices = (new BrasDeviceSelector($settings))->devices();
$query = new PppoeSessionQuery($settings);

if ($settings->cacheTtl() < 1) {
    fwrite(STDERR, "Cache TTL is 0, results cannot be stored. Set a TTL above the cron interval.\n");
    exit(1);
}

if ($devices->isEmpty()) {
    fwrite(STDERR, "No devices match the plugin settings\n");
    exit(1);
}

$failed = 0;

foreach ($devices as $device) {
    $started = microtime(true);

    try {
        $query->warm($device);
        $statistics = $query->statistics($device);
        $elapsed = round(microtime(true) - $started, 1);

        printf(
            "%-40s %s in %ss\n",
            $device->hostname,
            $statistics['available']
                ? number_format($statistics['total']) . ' sessions'
                : 'no data: ' . $statistics['error'],
            $elapsed
        );

        if (! $statistics['available']) {
            $failed++;
        }
    } catch (Throwable $exception) {
        $failed++;
        fwrite(STDERR, sprintf("%-40s failed: %s\n", $device->hostname, $exception->getMessage()));
    }
}

exit($failed > 0 ? 1 : 0);
