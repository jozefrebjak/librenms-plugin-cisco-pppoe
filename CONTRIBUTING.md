# Contributing

Issues and pull requests are welcome. A few things are specific to LibreNMS
plugins and easy to trip over.

## Plugin layout is enforced

LibreNMS validates the structure before it will install a plugin, and every name
is case sensitive:

```
DeviceOverview.php
Menu.php
Page.php
Settings.php
resources/views/device-overview.blade.php
resources/views/menu.blade.php
resources/views/page.blade.php
resources/views/settings.blade.php
```

**Only hook classes belong in the plugin root.** `PluginProvider::loadLocalPlugins()`
globs `app/Plugins/*/*.php` and throws `PluginDoesNotImplementHookException` for any
class that does not implement a hook interface, which takes down the whole provider.
Helpers go in `Support/`, scripts in `bin/`. CI checks this.

## Hook rules

- `handle()` is `final`. Override `data()` and `authorize()` only.
- Hooks are invoked through `app()->call()`, so arguments come from the service
  container. Type hint the user as `Illuminate\Contracts\Auth\Authenticatable`,
  **not** `App\Models\User` — the model is not bound, so the container would hand
  you an empty instance whose `can()` always returns false.
- Any argument outside the parent signature needs a default value.
- `SettingsHook::handle()` calls `data()` twice and returns the second result,
  feeding the first result back in as `$settings`. See `PluginSettings::unwrap()`.

## SNMP

- Poll numerically. LibreNMS ships neither `CISCO-PPPOE-MIB` nor
  `CISCO-SUBSCRIBER-SESSION-MIB`; the files in `mibs/` are reference material used
  to derive the OIDs in `Support/Oids.php` and are never loaded at runtime.
- Do not chain `->numeric()->hideMib()`. Both set the same option and `hideMib()`
  wins, which replaces numeric OIDs with a bare suffix.
- Add a comment to any new OID in `Support/Oids.php` saying what it returns.

## Style

- Code, comments and commit messages in English.
- No abbreviated variable names. `$query`, not `$q`.
- Conventional commits: `feat:`, `fix:`, `docs:`, `refactor:`, `chore:`, `test:`.
- Keep logic in `Support/` and the hook `data()` methods, not in blade views.

## Before opening a pull request

```bash
find . -name '*.php' -not -path './mibs/*' -print0 | xargs -0 -n1 php -l
```

Say which LibreNMS version and which Cisco platform and IOS-XE release you tested
against. The plugin is developed against LibreNMS 26.8 and Cisco ASR1000 running
IOS-XE 15.5(3)S; other platforms may populate these MIBs differently.
