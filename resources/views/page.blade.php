{{-- layouts.librenmsv1 yields the content without a wrapper, so the plugin has to
     bring its own container, the same way the core plugin admin page does. --}}
<div class="container-fluid" style="padding-top: 15px;">

<div class="panel panel-default">
    <div class="panel-heading">
        <strong>{{ $title }}</strong>
        <span class="pull-right">
            {{ number_format($totals['total']) }} active
            &middot; PTA {{ number_format($totals['pta']) }}
            &middot; FWDED {{ number_format($totals['fwded']) }}
            &middot; TRANS {{ number_format($totals['trans']) }}
        </span>
    </div>

    @if (empty($rows))
        <div class="panel-body">
            <span class="text-muted">
                No BRAS devices match the plugin settings. Adjust the device selection under
                Settings &rarr; Plugins &rarr; CiscoPppoe.
            </span>
        </div>
    @else
        <table class="table table-condensed table-hover" style="margin-bottom: 0;">
            <thead>
                <tr>
                    <th>Device</th>
                    <th class="text-right">Active</th>
                    <th class="text-right">PTA</th>
                    <th class="text-right">FWDED</th>
                    <th class="text-right">TRANS</th>
                    <th class="text-right">Limit</th>
                    <th style="width: 180px;">Utilisation</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr @class(['info' => $row['selected']])>
                        <td>
                            <a href="{{ $row['device_url'] }}">{{ $row['name'] }}</a>
                            @if ($row['hostname'] !== $row['name'])
                                <small class="text-muted">{{ $row['hostname'] }}</small>
                            @endif
                        </td>
                        @if (! $row['statistics']['available'])
                            <td colspan="6" class="text-muted">{{ $row['statistics']['error'] }}</td>
                        @else
                            <td class="text-right">{{ number_format($row['statistics']['total']) }}</td>
                            <td class="text-right">{{ number_format($row['statistics']['pta']) }}</td>
                            <td class="text-right">{{ number_format($row['statistics']['fwded']) }}</td>
                            <td class="text-right">{{ number_format($row['statistics']['trans']) }}</td>
                            <td class="text-right">
                                {{ $row['statistics']['limit'] ? number_format($row['statistics']['limit']) : 'no limit' }}
                            </td>
                            <td>
                                @if ($row['statistics']['limit'])
                                    {{-- Label sits outside the bar: at a fraction of a
                                         percent the bar is too narrow to hold text. --}}
                                    <div class="progress" style="margin-bottom: 2px; height: 10px;">
                                        <div class="progress-bar progress-bar-{{ $row['statistics']['utilization_class'] }}"
                                             role="progressbar"
                                             style="width: {{ $row['statistics']['utilization_width'] }}%; min-width: 2px;">
                                        </div>
                                    </div>
                                    <small class="text-muted">{{ $row['statistics']['utilization'] }}%</small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        @endif
                        <td class="text-right">
                            <a href="{{ $row['detail_url'] }}">Interfaces</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

@if ($selected)
    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>
                <a href="{{ $selected['device_url'] }}">{{ $selected['name'] }}</a>
                &mdash; sessions per interface
            </strong>
            <span class="pull-right">
                <a href="{{ $selected['refresh_url'] }}">Refresh</a>
                &middot;
                <a href="{{ $base_url }}">Close</a>
            </span>
        </div>

        @if (empty($selected['statistics']['interfaces']))
            <div class="panel-body">
                <span class="text-muted">
                    {{ $selected['statistics']['error'] ?? 'Device returned no per-interface PPPoE data.' }}
                </span>
            </div>
        @else
            <table class="table table-condensed table-hover" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th>Interface</th>
                        <th class="text-right">ifIndex</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">PTA</th>
                        <th class="text-right">FWDED</th>
                        <th class="text-right">TRANS</th>
                        <th class="text-right">Loss threshold</th>
                        <th class="text-right">Loss %</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($selected['statistics']['interfaces'] as $interface)
                        <tr>
                            <td>{{ $interface['name'] }}</td>
                            <td class="text-right">{{ $interface['ifIndex'] }}</td>
                            <td class="text-right">{{ number_format($interface['total']) }}</td>
                            <td class="text-right">{{ number_format($interface['pta']) }}</td>
                            <td class="text-right">{{ number_format($interface['fwded']) }}</td>
                            <td class="text-right">{{ number_format($interface['trans']) }}</td>
                            <td class="text-right">{{ $interface['loss_threshold'] ?: '-' }}</td>
                            <td class="text-right">{{ $interface['loss_percent'] ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>Subscriber sessions</strong>
            @if ($selected['sessions']['enabled'] && $selected['sessions']['available'])
                <span class="pull-right">
                    {{ number_format($selected['sessions']['total']) }} PPPoE sessions
                    @if ($selected['sessions']['truncated'])
                        &middot; showing first {{ number_format($selected['sessions']['limit']) }}
                    @endif
                </span>
            @endif
        </div>

        @if (! $selected['sessions']['enabled'])
            <div class="panel-body">
                <span class="text-muted">
                    The per-subscriber listing is disabled. Enable it under Settings &rarr; Plugins &rarr;
                    CiscoPppoe. It walks CISCO-SUBSCRIBER-SESSION-MIB, which is slow on a BRAS with
                    tens of thousands of subscribers.
                </span>
            </div>
        @elseif (! $selected['sessions']['available'])
            <div class="panel-body">
                <span class="text-muted">{{ $selected['sessions']['error'] }}</span>
            </div>
        @else
            <table class="table table-condensed table-hover table-striped" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>State</th>
                        <th>IP address</th>
                        <th>MAC address</th>
                        <th class="text-right">ifIndex</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($selected['sessions']['rows'] as $session)
                        <tr>
                            <td>{{ $session['username'] ?: '-' }}</td>
                            <td>
                                <span @class([
                                    'label',
                                    'label-success' => $session['state'] === 'up',
                                    'label-warning' => $session['state'] === 'pending',
                                    'label-default' => ! in_array($session['state'], ['up', 'pending'], true),
                                ])>{{ $session['state'] }}</span>
                            </td>
                            <td>{{ $session['ip'] ?: '-' }}</td>
                            <td>{{ $session['mac'] ?: '-' }}</td>
                            <td class="text-right">{{ $session['ifIndex'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endif

</div>
