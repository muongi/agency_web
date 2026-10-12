{{-- Shared filter bar. Params: $route, $f, $options, $show (array of agent|terminal|product), $exportLabel (optional) --}}
@php $show = $show ?? ['agent', 'terminal', 'product']; @endphp
<form method="GET" action="{{ route($route) }}" style="margin-bottom:20px;padding:16px;background:var(--bg-tertiary);border-radius:8px;">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;">
        <div class="form-group">
            <label class="form-label" for="from">{{ __('common.sunu_report_from') }}</label>
            <input type="date" id="from" name="from" class="form-control" value="{{ $f['from']->format('Y-m-d') }}">
        </div>
        <div class="form-group">
            <label class="form-label" for="to">{{ __('common.sunu_report_to') }}</label>
            <input type="date" id="to" name="to" class="form-control" value="{{ $f['to']->format('Y-m-d') }}">
        </div>
        @if(in_array('agent', $show))
            <div class="form-group">
                <label class="form-label" for="agent_id">{{ __('common.agent') }}</label>
                <select id="agent_id" name="agent_id" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach($options['agents'] as $a)
                        <option value="{{ $a->id }}" {{ $f['agent_id'] === $a->id ? 'selected' : '' }}>{{ $a->full_name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if(in_array('terminal', $show))
            <div class="form-group">
                <label class="form-label" for="terminal_id">{{ __('common.sunu_report_terminal') }}</label>
                <select id="terminal_id" name="terminal_id" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach($options['terminals'] as $t)
                        <option value="{{ $t->id }}" {{ $f['terminal_id'] === $t->id ? 'selected' : '' }}>{{ $t->device_alias ?: $t->serial_number }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if(in_array('product', $show))
            <div class="form-group">
                <label class="form-label" for="product_id">{{ __('common.sunu_report_product') }}</label>
                <select id="product_id" name="product_id" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach($options['products'] as $p)
                        <option value="{{ $p->id }}" {{ $f['product_id'] === $p->id ? 'selected' : '' }}>{{ $p->localizedName() }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>
    <div class="btn-row">
        <button type="submit" class="btn">{{ __('common.filter') }}</button>
        <a href="{{ route($route) }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
        @if(auth()->user()->hasPermission('export-sunu-reports'))
        <a href="{{ route($route, array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-secondary">{{ $exportLabel ?? __('common.sunu_report_export') }} CSV</a>
        <a href="{{ route($route, array_merge(request()->query(), ['export' => 'excel'])) }}" class="btn btn-secondary">{{ $exportLabel ?? __('common.sunu_report_export') }} Excel</a>
        @endif
    </div>
</form>
