# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-09-18

First release. Developed against LibreNMS 26.8.2 and Cisco ASR1000 running
IOS-XE 15.5(3)S.

### Added

- Device overview panel with active sessions, the PTA / FWDED / TRANS breakdown,
  the configured limit and utilisation. Hidden on devices that are not a BRAS or
  do not answer.
- Plugin page listing every BRAS with totals, and a per-interface drill-down.
  Interfaces carrying no sessions are hidden behind a toggle.
- Opt-in per-subscriber listing from `CISCO-SUBSCRIBER-SESSION-MIB` with username,
  state and IP address.
- Device selection either automatically from OS and sysName, or from an explicit
  list, with a preview of what the saved filters resolve to.
- **Create custom OIDs** button that registers `cPppoeSystemCurrSessions` on every
  matching BRAS so LibreNMS graphs and alerts on it. Only ever adds, never modifies
  or removes, so no RRD history can be lost.
- `bin/warm-cache.php` for collecting in the background from cron.
- Reference MIB files under `mibs/`, used to derive the numeric OIDs.

### Notes

- The subscriber listing is read from cache only, so opening a device never waits
  on SNMP. A **Poll now** button covers the case where fresh data is worth the wait.
- All polling is numeric. LibreNMS ships neither MIB, and neither is loaded at
  runtime.
- IOS-XE 15.5(3)S returns an empty `csubSessionMacAddress`, so that column is not
  walked, and reports an unset session limit as the Unsigned32 maximum rather than
  the `0` the MIB describes. Both are treated as no limit.

[1.0.0]: https://github.com/jozefrebjak/librenms-plugin-cisco-pppoe/releases/tag/v1.0.0
