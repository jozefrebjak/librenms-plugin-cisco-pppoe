<div class="panel panel-default">
    <div class="panel-heading">
        <strong>{{ $plugin_name }} settings</strong>
        <span class="pull-right">{{ number_format($matched_devices) }} device(s) currently match</span>
    </div>

    <div class="panel-body">
        <form method="post" class="form-horizontal">
            @csrf

            <div class="form-group">
                <label class="col-sm-3 control-label" for="device_selection">Device selection</label>
                <div class="col-sm-6">
                    <select class="form-control" id="device_selection" name="settings[device_selection]">
                        <option value="{{ $selection_auto }}" @selected($settings['device_selection'] === $selection_auto)>
                            Automatic (match on OS and sysName)
                        </option>
                        <option value="{{ $selection_manual }}" @selected($settings['device_selection'] === $selection_manual)>
                            Manual (pick devices below)
                        </option>
                    </select>
                    <span class="help-block">
                        Automatic keeps new BRAS devices appearing on their own, manual gives you an explicit list.
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label" for="os_filter">OS filter</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" id="os_filter" name="settings[os_filter]"
                           value="{{ $settings['os_filter'] }}" placeholder="iosxe">
                    <span class="help-block">
                        Comma separated LibreNMS OS names, for example <code>iosxe</code> or <code>iosxe,ios</code>.
                        Leave empty to match any OS. Also limits the device list below.
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label" for="sysname_filter">sysName contains</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" id="sysname_filter" name="settings[sysname_filter]"
                           value="{{ $settings['sysname_filter'] }}" placeholder="bras">
                    <span class="help-block">
                        Only used in automatic mode. Matched case insensitively against sysName, falling back to
                        the hostname. Leave empty to accept every device of the OS above.
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label" for="device_ids">BRAS devices</label>
                <div class="col-sm-6">
                    <select class="form-control" id="device_ids" name="settings[device_ids][]" multiple size="12">
                        @foreach ($selectable_devices as $selectable)
                            <option value="{{ $selectable['device_id'] }}"
                                    @selected(in_array($selectable['device_id'], $settings['device_ids'], true))>
                                {{ $selectable['label'] }} [{{ $selectable['os'] }}]
                            </option>
                        @endforeach
                    </select>
                    <span class="help-block">
                        Only used in manual mode. Hold Ctrl (Cmd on macOS) to select several devices.
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label" for="cache_ttl">Cache TTL (seconds)</label>
                <div class="col-sm-3">
                    <input type="number" class="form-control" id="cache_ttl" name="settings[cache_ttl]"
                           min="0" step="1" value="{{ $settings['cache_ttl'] }}">
                    <span class="help-block">
                        How long SNMP results are reused. 0 polls the BRAS on every page load, which is
                        not recommended.
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label" for="session_walk">Per-subscriber listing</label>
                <div class="col-sm-6">
                    <div class="checkbox">
                        <label>
                            <input type="hidden" name="settings[session_walk]" value="0">
                            <input type="checkbox" id="session_walk" name="settings[session_walk]" value="1"
                                   @checked($settings['session_walk'])>
                            Walk CISCO-SUBSCRIBER-SESSION-MIB for individual sessions
                        </label>
                    </div>
                    <span class="help-block text-warning">
                        Off by default. This walks one row per subscriber, so on a BRAS with tens of thousands
                        of sessions it is slow and can hold up the poller. Keep the cache TTL high when enabling it.
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label" for="session_limit">Session rows shown</label>
                <div class="col-sm-3">
                    <input type="number" class="form-control" id="session_limit" name="settings[session_limit]"
                           min="1" max="{{ $session_limit_max }}" step="1" value="{{ $settings['session_limit'] }}">
                    <span class="help-block">Maximum number of sessions rendered on the page.</span>
                </div>
            </div>

            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-6">
                    <button type="submit" class="btn btn-primary">Save settings</button>
                </div>
            </div>
        </form>
    </div>
</div>
