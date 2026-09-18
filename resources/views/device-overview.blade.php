<div class="panel panel-default panel-condensed">
    <div class="panel-heading">
        <strong>{{ $title }}</strong>
        <a href="{{ $url }}" class="pull-right">Detail</a>
    </div>
    <div class="panel-body">
        @if (! $statistics['available'])
            <span class="text-muted">{{ $statistics['error'] ?? 'No PPPoE data available.' }}</span>
        @else
            <div class="row text-center">
                <div class="col-xs-3">
                    <div style="font-size: 26px; line-height: 1.1;">{{ number_format($statistics['total']) }}</div>
                    <small class="text-muted">Active</small>
                </div>
                <div class="col-xs-3">
                    <div style="font-size: 26px; line-height: 1.1;">{{ number_format($statistics['pta']) }}</div>
                    <small class="text-muted">PTA</small>
                </div>
                <div class="col-xs-3">
                    <div style="font-size: 26px; line-height: 1.1;">{{ number_format($statistics['fwded']) }}</div>
                    <small class="text-muted">FWDED</small>
                </div>
                <div class="col-xs-3">
                    <div style="font-size: 26px; line-height: 1.1;">{{ number_format($statistics['trans']) }}</div>
                    <small class="text-muted">TRANS</small>
                </div>
            </div>

            @if ($statistics['limit'])
                <div class="row" style="margin-top: 10px;">
                    <div class="col-xs-12">
                        <div class="progress" style="margin-bottom: 5px;">
                            <div class="progress-bar progress-bar-{{ $statistics['utilization_class'] }}"
                                 role="progressbar"
                                 aria-valuenow="{{ $statistics['utilization'] }}"
                                 aria-valuemin="0"
                                 aria-valuemax="100"
                                 style="width: {{ $statistics['utilization_width'] }}%;">
                                {{ $statistics['utilization'] }}%
                            </div>
                        </div>
                        <small class="text-muted">
                            {{ number_format($statistics['total']) }} /
                            {{ number_format($statistics['limit']) }}
                            {{ $statistics['limit_source'] === 'threshold' ? 'threshold' : 'max allowed' }} sessions
                            @if ($statistics['high_water'] !== null)
                                &middot; high water {{ number_format($statistics['high_water']) }}
                            @endif
                            @if ($statistics['exceeded_errors'])
                                &middot; <span class="text-danger">{{ number_format($statistics['exceeded_errors']) }} rejected</span>
                            @endif
                        </small>
                    </div>
                </div>
            @elseif ($statistics['high_water'] !== null)
                <div class="row" style="margin-top: 10px;">
                    <div class="col-xs-12">
                        <small class="text-muted">
                            No session limit configured &middot; high water {{ number_format($statistics['high_water']) }}
                        </small>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
