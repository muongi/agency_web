@php
    $requestMappings = old('request_mappings', $mapping->request_mappings ?? [['source' => '', 'target' => '', 'transform' => 'none', 'required' => false, 'default' => '']]);
    if (empty($requestMappings)) {
        $requestMappings = [['source' => '', 'target' => '', 'transform' => 'none', 'required' => false, 'default' => '']];
    }
    $responseMappings = old('response_mappings', $mapping->response_mappings ?? [['cbs_path' => '', 'internal_key' => '']]);
    if (empty($responseMappings)) {
        $responseMappings = [['cbs_path' => '', 'internal_key' => '']];
    }
@endphp

<div class="form-group">
    <label class="form-label" for="http_method">{{ __('common.cbs_http_method') }} <span style="color: #ff6b6b;">*</span></label>
    <select id="http_method" name="http_method" class="form-control" required>
        @foreach(['POST', 'GET', 'PUT', 'PATCH'] as $method)
            <option value="{{ $method }}" {{ old('http_method', $mapping->http_method ?? 'POST') === $method ? 'selected' : '' }}>{{ $method }}</option>
        @endforeach
    </select>
</div>

<div class="form-group">
    <label class="form-label" for="endpoint_path">{{ __('common.cbs_endpoint_path') }} <span style="color: #ff6b6b;">*</span></label>
    <input type="text" id="endpoint_path" name="endpoint_path" class="form-control" value="{{ old('endpoint_path', $mapping->endpoint_path ?? '') }}" placeholder="transactions/deposit" required>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
    <div class="form-group">
        <label class="form-label" for="success_path">{{ __('common.cbs_success_path') }}</label>
        <input type="text" id="success_path" name="success_path" class="form-control" value="{{ old('success_path', $mapping->success_path ?? 'success') }}" placeholder="success">
    </div>
    <div class="form-group">
        <label class="form-label" for="success_value">{{ __('common.cbs_success_value') }}</label>
        <input type="text" id="success_value" name="success_value" class="form-control" value="{{ old('success_value', $mapping->success_value ?? 'true') }}" placeholder="true">
    </div>
    <div class="form-group">
        <label class="form-label" for="error_message_path">{{ __('common.cbs_error_path') }}</label>
        <input type="text" id="error_message_path" name="error_message_path" class="form-control" value="{{ old('error_message_path', $mapping->error_message_path ?? 'message') }}" placeholder="message">
    </div>
</div>

<div class="form-group">
    <label class="form-label">
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $mapping->is_active ?? true) ? 'checked' : '' }}>
        {{ __('common.is_active') }}
    </label>
</div>

<div class="form-group" style="margin-top: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <label class="form-label" style="margin: 0;">{{ __('common.cbs_request_mappings') }}</label>
        <button type="button" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;" onclick="addRequestRow()">+ {{ __('common.add_row') }}</button>
    </div>
    <div id="request-mapping-rows">
        @foreach($requestMappings as $index => $row)
            <div class="mapping-row" style="display: grid; grid-template-columns: 2fr 2fr 1fr auto auto 2fr auto; gap: 8px; margin-bottom: 8px; align-items: end;">
                <div>
                    @if($index === 0)<small style="color: var(--text-muted);">{{ __('common.cbs_source_field') }}</small>@endif
                    <select name="request_mappings[{{ $index }}][source]" class="form-control">
                        <option value="">{{ __('common.cbs_literal_value') }}</option>
                        @foreach($sourceFields as $field)
                            <option value="{{ $field }}" {{ ($row['source'] ?? '') === $field ? 'selected' : '' }}>{{ $field }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    @if($index === 0)<small style="color: var(--text-muted);">{{ __('common.cbs_target_field') }}</small>@endif
                    <input type="text" name="request_mappings[{{ $index }}][target]" class="form-control" value="{{ $row['target'] ?? '' }}" placeholder="amount">
                </div>
                <div>
                    @if($index === 0)<small style="color: var(--text-muted);">{{ __('common.cbs_transform') }}</small>@endif
                    <select name="request_mappings[{{ $index }}][transform]" class="form-control">
                        @foreach($transforms as $transform)
                            <option value="{{ $transform }}" {{ ($row['transform'] ?? 'none') === $transform ? 'selected' : '' }}>{{ $transform }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    @if($index === 0)<small style="color: var(--text-muted);">{{ __('common.required') }}</small>@endif
                    <label style="display: flex; align-items: center; height: 38px;">
                        <input type="checkbox" name="request_mappings[{{ $index }}][required]" value="1" {{ !empty($row['required']) ? 'checked' : '' }}>
                    </label>
                </div>
                <div>
                    @if($index === 0)<small style="color: var(--text-muted);">{{ __('common.cbs_default') }}</small>@endif
                    <input type="text" name="request_mappings[{{ $index }}][default]" class="form-control" value="{{ $row['default'] ?? '' }}">
                </div>
                <div></div>
                <div>
                    @if($index === 0)<small>&nbsp;</small>@endif
                    <button type="button" class="btn btn-secondary" style="padding: 6px 10px;" onclick="this.closest('.mapping-row').remove()">×</button>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="form-group" style="margin-top: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <label class="form-label" style="margin: 0;">{{ __('common.cbs_response_mappings') }}</label>
        <button type="button" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;" onclick="addResponseRow()">+ {{ __('common.add_row') }}</button>
    </div>
    <div id="response-mapping-rows">
        @foreach($responseMappings as $index => $row)
            <div class="response-row" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 8px; margin-bottom: 8px; align-items: end;">
                <div>
                    @if($index === 0)<small style="color: var(--text-muted);">{{ __('common.cbs_response_path') }}</small>@endif
                    <input type="text" name="response_mappings[{{ $index }}][cbs_path]" class="form-control" value="{{ $row['cbs_path'] ?? '' }}" placeholder="transactionId">
                </div>
                <div>
                    @if($index === 0)<small style="color: var(--text-muted);">{{ __('common.cbs_internal_key') }}</small>@endif
                    <input type="text" name="response_mappings[{{ $index }}][internal_key]" class="form-control" value="{{ $row['internal_key'] ?? '' }}" placeholder="transactionId">
                </div>
                <div>
                    @if($index === 0)<small>&nbsp;</small>@endif
                    <button type="button" class="btn btn-secondary" style="padding: 6px 10px;" onclick="this.closest('.response-row').remove()">×</button>
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
const cbsSourceFields = @json($sourceFields);
const cbsTransforms = @json($transforms);
const cbsLiteralLabel = @json(__('common.cbs_literal_value'));

function addRequestRow() {
    const container = document.getElementById('request-mapping-rows');
    const index = container.querySelectorAll('.mapping-row').length;
    const sourceOptions = [`<option value="">${cbsLiteralLabel}</option>`]
        .concat(cbsSourceFields.map(f => `<option value="${f}">${f}</option>`)).join('');
    const transformOptions = cbsTransforms.map(t => `<option value="${t}">${t}</option>`).join('');
    container.insertAdjacentHTML('beforeend', `
        <div class="mapping-row" style="display: grid; grid-template-columns: 2fr 2fr 1fr auto auto 2fr auto; gap: 8px; margin-bottom: 8px; align-items: end;">
            <div><select name="request_mappings[${index}][source]" class="form-control">${sourceOptions}</select></div>
            <div><input type="text" name="request_mappings[${index}][target]" class="form-control" placeholder="amount"></div>
            <div><select name="request_mappings[${index}][transform]" class="form-control">${transformOptions}</select></div>
            <div><label style="display:flex;align-items:center;height:38px;"><input type="checkbox" name="request_mappings[${index}][required]" value="1"></label></div>
            <div><input type="text" name="request_mappings[${index}][default]" class="form-control"></div>
            <div></div>
            <div><button type="button" class="btn btn-secondary" style="padding: 6px 10px;" onclick="this.closest('.mapping-row').remove()">×</button></div>
        </div>
    `);
}

function addResponseRow() {
    const container = document.getElementById('response-mapping-rows');
    const index = container.querySelectorAll('.response-row').length;
    container.insertAdjacentHTML('beforeend', `
        <div class="response-row" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 8px; margin-bottom: 8px; align-items: end;">
            <div><input type="text" name="response_mappings[${index}][cbs_path]" class="form-control" placeholder="transactionId"></div>
            <div><input type="text" name="response_mappings[${index}][internal_key]" class="form-control" placeholder="transactionId"></div>
            <div><button type="button" class="btn btn-secondary" style="padding: 6px 10px;" onclick="this.closest('.response-row').remove()">×</button></div>
        </div>
    `);
}
</script>
