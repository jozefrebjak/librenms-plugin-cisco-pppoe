{{-- layouts.librenmsv1 yields the content without a wrapper, so the plugin has to
     bring its own container, the same way the core plugin admin page does. --}}
<div class="container">
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
                <label class="col-sm-3 control-label">Currently matching</label>
                <div class="col-sm-6">
                    @if (empty($matched_device_list))
                        <p class="form-control-static text-danger">
                            No devices match the saved settings.
                        </p>
                    @else
                        <ul class="list-unstyled form-control-static" style="margin-bottom: 0;">
                            @foreach ($matched_device_list as $matched)
                                <li>
                                    <a href="{{ $matched['url'] }}">{{ $matched['display'] }}</a>
                                    @if ($matched['secondary'])
                                        <small class="text-muted">{{ $matched['secondary'] }}</small>
                                    @endif
                                    @if ($matched['has_custom_oid'])
                                        <a href="{{ $matched['graph_url'] }}" class="label label-success">graph</a>
                                    @else
                                        <span class="label label-default">no graph</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <span class="help-block">
                        What the saved settings resolve to right now. Save to refresh this list.
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-3 control-label">Session history</label>
                <div class="col-sm-6">
                    @if (! empty($custom_oid_report))
                        <table class="table table-condensed" style="margin-bottom: 10px;">
                            @foreach ($custom_oid_report as $result)
                                <tr>
                                    <td>{{ $result['device'] }}</td>
                                    <td>
                                        <span @class([
                                            'label',
                                            'label-success' => $result['state'] === 'created',
                                            'label-default' => $result['state'] === 'exists',
                                            'label-danger' => $result['state'] === 'failed',
                                        ])>{{ $result['state'] }}</span>
                                    </td>
                                    <td class="text-muted">{{ $result['detail'] }}</td>
                                </tr>
                            @endforeach
                        </table>
                    @endif

                    <p class="form-control-static" style="padding-top: 0;">
                        <a href="{{ $create_custom_oids_url }}"
                           class="btn {{ $custom_oid_missing ? 'btn-primary' : 'btn-default' }}">
                            Create custom OIDs
                        </a>
                        @if ($custom_oid_missing)
                            <span class="text-warning">
                                {{ $custom_oid_missing }} device(s) without one
                            </span>
                        @else
                            <span class="text-success">All matching devices have one</span>
                        @endif
                    </p>

                    <span class="help-block">
                        The plugin keeps no history of its own. This registers
                        <code>{{ $custom_oid_description }}</code> as a LibreNMS custom OID on every
                        matching device, which the poller then graphs and can alert on. The value is
                        read from each device first, and existing entries are never changed or
                        removed.
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
                        Off by default, and only used when you open a device detail on the plugin page.
                        It walks five columns with one row per subscriber, so the page blocks until the
                        BRAS answers and the device carries extra SNMP load alongside its regular poll.
                        Keep the cache TTL high when enabling it.
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
</div>
