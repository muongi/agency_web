@php
    $authConfig = old('auth_config', $cbs->auth_config ?? []);
    if (!is_array($authConfig)) {
        $authConfig = [];
    }
@endphp

<div class="form-group">
    <label class="form-label" for="driver">{{ __('common.cbs_driver') }} <span class="text-error">*</span></label>
    <select id="driver" name="driver" class="form-control" required>
        <option value="configurable" {{ old('driver', $cbs->driver ?? 'configurable') === 'configurable' ? 'selected' : '' }}>{{ __('common.cbs_driver_configurable') }}</option>
        <option value="kato" {{ old('driver', $cbs->driver ?? '') === 'kato' ? 'selected' : '' }}>{{ __('common.cbs_driver_kato') }}</option>
    </select>
</div>

<div id="configurable-cbs-fields">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
        <div class="form-group">
            <label class="form-label" for="auth_type">{{ __('common.cbs_auth_type') }}</label>
            <select id="auth_type" name="auth_type" class="form-control">
                @foreach(['jwt_bearer' => __('common.cbs_auth_jwt_bearer'), 'basic' => __('common.cbs_auth_basic'), 'oauth_password' => __('common.cbs_auth_oauth_password')] as $value => $label)
                    <option value="{{ $value }}" {{ old('auth_type', $cbs->auth_type ?? 'jwt_bearer') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="timeout_seconds">{{ __('common.cbs_timeout_seconds') }}</label>
            <input type="number" id="timeout_seconds" name="timeout_seconds" class="form-control" min="5" max="300" value="{{ old('timeout_seconds', $cbs->timeout_seconds ?? 30) }}">
        </div>
    </div>

    <div id="oauth-config-fields" style="margin-top: 10px; padding: 15px; background: var(--bg-tertiary); border-radius: 4px;">
        <h3 style="color: var(--accent); font-size: 14px; margin-bottom: 10px;">{{ __('common.cbs_auth_config') }}</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
            <div class="form-group">
                <label class="form-label" for="auth_token_endpoint">{{ __('common.cbs_token_endpoint') }}</label>
                <input type="text" id="auth_token_endpoint" name="auth_token_endpoint" class="form-control" value="{{ old('auth_token_endpoint', $authConfig['token_endpoint'] ?? '') }}" placeholder="auth/login">
            </div>
            <div class="form-group">
                <label class="form-label" for="auth_token_path">{{ __('common.cbs_token_path') }}</label>
                <input type="text" id="auth_token_path" name="auth_token_path" class="form-control" value="{{ old('auth_token_path', $authConfig['token_path'] ?? 'token') }}" placeholder="data.token">
            </div>
            <div class="form-group">
                <label class="form-label" for="auth_username_field">{{ __('common.cbs_username_field') }}</label>
                <input type="text" id="auth_username_field" name="auth_username_field" class="form-control" value="{{ old('auth_username_field', $authConfig['username_field'] ?? 'username') }}">
            </div>
            <div class="form-group">
                <label class="form-label" for="auth_password_field">{{ __('common.cbs_password_field') }}</label>
                <input type="text" id="auth_password_field" name="auth_password_field" class="form-control" value="{{ old('auth_password_field', $authConfig['password_field'] ?? 'password') }}">
            </div>
            <div class="form-group">
                <label class="form-label" for="auth_token_expires_path">{{ __('common.cbs_token_expires_path') }}</label>
                <input type="text" id="auth_token_expires_path" name="auth_token_expires_path" class="form-control" value="{{ old('auth_token_expires_path', $authConfig['token_expires_path'] ?? '') }}">
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const driverSelect = document.getElementById('driver');
    const configurableFields = document.getElementById('configurable-cbs-fields');
    const authTypeSelect = document.getElementById('auth_type');
    const oauthFields = document.getElementById('oauth-config-fields');
    if (!driverSelect) return;

    function toggleDriverFields() {
        const isKato = driverSelect.value === 'kato';
        if (configurableFields) configurableFields.style.display = isKato ? 'none' : 'block';
    }

    function toggleOAuthFields() {
        if (!authTypeSelect || !oauthFields) return;
        const showOAuth = authTypeSelect.value === 'oauth_password' || authTypeSelect.value === 'jwt_bearer';
        oauthFields.style.display = showOAuth ? 'block' : 'none';
    }

    driverSelect.addEventListener('change', toggleDriverFields);
    if (authTypeSelect) authTypeSelect.addEventListener('change', toggleOAuthFields);
    toggleDriverFields();
    toggleOAuthFields();
})();
</script>
