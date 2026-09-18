# CiscoPppoe — LibreNMS v2 plugin

PPPoE session overview for Cisco ASR1000 (IOS-XE) BRAS devices over SNMP.

The plugin adds:

- **a device overview panel** — active session count, PTA / FWDED / TRANS breakdown,
  configured limit and utilisation; only shown on devices that actually return
  PPPoE data,
- **a standalone page** listing every BRAS with a drill-down into the per-interface
  breakdown,
- **an optional per-subscriber listing** (username / IP / MAC / state),
- **a LibreNMS menu entry**.

## Requirements

- LibreNMS with v2 plugin support (`app/Plugins/`),
- SNMP access to the BRAS (the credentials the device already uses in LibreNMS),
- Cisco IOS-XE with `CISCO-PPPOE-MIB` support.

Quick check that a device returns data:

```bash
snmpwalk -v2c -c <community> <bras-ip> .1.3.6.1.4.1.9.9.194.1.1.1.0
```

## Installation (Docker)

Clone the repository on the host and bind mount it into the container.
**Do not use `/data/plugins`** — in `librenms/docker` that path maps to
`html/plugins/`, which is the legacy v1 system. v2 plugins live in `app/Plugins/`.

```bash
git clone <repo-url> /opt/librenms/plugins/CiscoPppoe
```

Ownership has to match the uid/gid of the librenms user inside the container:

```bash
chown -R 1000:1000 /opt/librenms/plugins/CiscoPppoe
```

In `docker-compose.yml`, under the `librenms` service:

```yaml
services:
  librenms:
    volumes:
      - /opt/librenms/plugins/CiscoPppoe:/opt/librenms/app/Plugins/CiscoPppoe
```

The plugin has no poller component — it reads SNMP when a page is rendered — so it
does not need to be mounted into `dispatcher`.

```bash
cd /opt/librenms
docker compose up -d librenms
docker compose exec -u librenms librenms php artisan optimize:clear
```

Then enable it in the UI under **Overview → Plugins** (or **Settings → Plugins**).
Configuration lives behind the *Settings* button next to the plugin.

## Settings

| Setting | Default | Description |
|---|---|---|
| Device selection | `Automatic` | `Automatic` matches on OS and sysName, `Manual` uses an explicit device list |
| OS filter | `iosxe` | Comma separated LibreNMS OS names. Empty matches any OS. Also limits the manual device picker |
| sysName contains | `bras` | Substring of sysName, case insensitive, falling back to the hostname. Empty accepts every device of the OS above |
| BRAS devices | — | Explicit device list, only used in `Manual` mode |
| Cache TTL | `60` s | How long SNMP results are reused. `0` polls on every page load, which is not recommended |
| Per-subscriber listing | off | Enables the `CISCO-SUBSCRIBER-SESSION-MIB` walk |
| Session rows shown | `500` | How many rows the listing renders (hard ceiling 20000) |

### Why the per-subscriber listing is off by default

`csubSessionTable` holds one row per subscriber. The plugin walks five of its columns
(`type`, `state`, `username`, `mac`, `ip`), so a BRAS with tens of thousands of
sessions means tens of thousands of varbinds per column.

Two things to be aware of, neither of which touches the LibreNMS poller process:

- the walk runs while the page renders, so the page blocks until the BRAS answers,
- the BRAS carries that SNMP load on top of its regular poll.

The walk only runs when you open a device detail, never for the device list or the
overview panel. When enabling it, keep the cache TTL high (300 s or more).

Check the device supports the MIB before enabling, starting with your smallest BRAS:

```bash
snmpbulkwalk -v2c -c <community> <bras-ip> .1.3.6.1.4.1.9.9.786.1.1.1.1.24 | head
```

## OIDs used

Derived with `snmptranslate` from the official Cisco MIB files in `mibs/` and checked
against a live ASR1000. The plugin polls **numerically** — the MIB files in this repo
are reference material and are never loaded at runtime.

### CISCO-PPPOE-MIB (`1.3.6.1.4.1.9.9.194`)

| OID | Object | Description |
|---|---|---|
| `.1.3.6.1.4.1.9.9.194.1.1.1.0` | `cPppoeSystemCurrSessions` | PPPoE sessions currently active on the device |
| `.1.3.6.1.4.1.9.9.194.1.1.2.0` | `cPppoeSystemHighWaterSessions` | highest concurrent count seen |
| `.1.3.6.1.4.1.9.9.194.1.1.3.0` | `cPppoeSystemMaxAllowedSessions` | session limit (0 means no limit) |
| `.1.3.6.1.4.1.9.9.194.1.1.4.0` | `cPppoeSystemThresholdSessions` | trap watermark |
| `.1.3.6.1.4.1.9.9.194.1.1.5.0` | `cPppoeSystemExceededSessionErrors` | sessions refused because the limit was reached |
| `.1.3.6.1.4.1.9.9.194.1.4.1.1` | `cPppoeSessionsPerInterfaceEntry` | table indexed by ifIndex, columns 1–6: total, PTA, FWDED, TRANS, loss threshold, loss % |

### CISCO-SUBSCRIBER-SESSION-MIB (`1.3.6.1.4.1.9.9.786`)

| OID | Object |
|---|---|
| `.1.3.6.1.4.1.9.9.786.1.1.1.1.1` | `csubSessionType` (PPPoE = 4) |
| `.1.3.6.1.4.1.9.9.786.1.1.1.1.3` | `csubSessionState` (other 1, pending 2, up 3) |
| `.1.3.6.1.4.1.9.9.786.1.1.1.1.10` | `csubSessionMacAddress` |
| `.1.3.6.1.4.1.9.9.786.1.1.1.1.13` | `csubSessionNativeIpAddr` |
| `.1.3.6.1.4.1.9.9.786.1.1.1.1.24` | `csubSessionUsername` |

The full annotated list is in [`Support/Oids.php`](Support/Oids.php).

`csubSessionTable` (`.1.3.6.1.4.1.9.9.786.1.1.1`) is `MAX-ACCESS not-accessible`, so
walking the table OID directly returns nothing — go to the columnar objects instead.

## Custom OID (without the plugin)

For just a session count graph, add a Custom OID under *Device → Edit → Custom OID*:

- **OID:** `.1.3.6.1.4.1.9.9.194.1.1.1.0`
- **Data Type:** `GAUGE`
- **Unit:** `sessions`

## Repository layout

```
DeviceOverview.php                       hook: device overview panel
Menu.php                                 hook: menu entry
Page.php                                 hook: standalone page
Settings.php                             hook: configuration
Support/Oids.php                         verified numeric OIDs
Support/SnmpTable.php                    net-snmp output parsing (pure, testable without SNMP)
Support/PluginSettings.php               typed access to the stored settings
Support/BrasDeviceSelector.php           BRAS device selection
Support/PppoeSessionQuery.php            SNMP queries and caching
Support/Presenter.php                    formatting helpers for the views
resources/views/*.blade.php              Bootstrap 3 views
mibs/                                    reference MIB files, not a runtime dependency
```

## Troubleshooting

**The plugin does not show up in the UI**

```bash
docker compose exec -u librenms librenms php artisan optimize:clear
```

Check the file names — the structure is case sensitive and is validated before the
plugin can be installed.

**The plugin disabled itself**

LibreNMS disables a plugin that throws. Turn on error reporting:

```bash
docker compose exec -u librenms librenms php artisan config:set plugins.show_errors true
```

then check `logs/librenms.log`.

**The panel does not appear on a device**

`DeviceOverview::authorize()` returns false when the device does not match the
selection or returns no SNMP data. Verify that:

1. the device matches the OS and sysName filters (or is in the manual list),
2. `snmpwalk -v2c -c <community> <ip> .1.3.6.1.4.1.9.9.194.1.1.1.0` returns a value,
3. the device is not disabled.

**The page shows stale numbers**

Data is cached for the configured TTL. The device detail section has a *Refresh* link
that drops the cache for that device.

**The per-interface table is empty but the total is correct**

Some IOS-XE versions do not populate `cPppoeSessionsPerInterfaceTable`. The total then
comes from `cPppoeSystemCurrSessions` and the PTA/FWDED/TRANS breakdown stays at zero.

## Screenshots

_To be added after deployment:_

- `docs/device-overview.png` — device overview panel
- `docs/page.png` — BRAS list
- `docs/settings.png` — plugin settings
