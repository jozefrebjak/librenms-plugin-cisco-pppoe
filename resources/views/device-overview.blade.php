<div class="panel panel-default panel-condensed">
    <div class="panel-heading">
        <strong>{{ $title }}</strong>
        <span class="pull-right">
            @if ($graph_url)
                <a href="{{ $graph_url }}">Graph</a> &middot;
            @endif
            <a href="{{ $url }}">Detail</a>
        </span>
    </div>
    <div class="panel-body">
        @if (! $statistics['available'])
            <span class="text-muted">{{ $statistics['error'] ?? 'No PPPoE data available.' }}</span>
        @else
            <div class="row text-center">
                @foreach (['Active' => 'total', 'PTA' => 'pta', 'FWDED' => 'fwded', 'TRANS' => 'trans'] as $label => $key)
                    <div class="col-xs-3">
                        <div style="font-size: 26px; line-height: 1.1;">{{ number_format($statistics[$key]) }}</div>
                        <small class="text-muted">
                            <abbr title="{{ $glossary[$label] }}">{{ $label }}</abbr>
                        </small>
                    </div>
                @endforeach
            </div>

            <div class="row" style="margin-top: 6px;">
                <div class="col-xs-12 text-center">
                    <small class="text-muted">
                        @foreach ($glossary_short as $term => $meaning)
                            <strong>{{ $term }}</strong> {{ $meaning }}@if (! $loop->last) &middot; @endif
                        @endforeach
                    </small>
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
