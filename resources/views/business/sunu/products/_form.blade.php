@csrf
@if($method === 'PUT')
    @method('PUT')
@endif

<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:0 20px;">
    <div class="form-group">
        <label class="form-label" for="code">{{ __('common.sunu_product_code') }}</label>
        <input type="text" id="code" name="code" class="form-control" required
               value="{{ old('code', $product->code) }}" placeholder="MICRO_QUIETUDE"
               @if($product->exists) readonly @endif>
        <small style="color:var(--text-muted); font-size:12px;">{{ __('common.sunu_product_code_help') }}</small>
        @error('code')<div style="color:var(--danger-color, #c62828); font-size:12px;">{{ $message }}</div>@enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="display_order">{{ __('common.sunu_display_order') }}</label>
        <input type="number" id="display_order" name="display_order" class="form-control" min="0" max="9999"
               value="{{ old('display_order', $product->display_order) }}">
        @error('display_order')<div style="color:var(--danger-color, #c62828); font-size:12px;">{{ $message }}</div>@enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="name_fr">{{ __('common.sunu_name_fr') }}</label>
        <input type="text" id="name_fr" name="name_fr" class="form-control" required
               value="{{ old('name_fr', $product->name_fr) }}" placeholder="Micro Quiétude">
        @error('name_fr')<div style="color:var(--danger-color, #c62828); font-size:12px;">{{ $message }}</div>@enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="name_en">{{ __('common.sunu_name_en') }}</label>
        <input type="text" id="name_en" name="name_en" class="form-control"
               value="{{ old('name_en', $product->name_en) }}">
        @error('name_en')<div style="color:var(--danger-color, #c62828); font-size:12px;">{{ $message }}</div>@enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="visa_number">{{ __('common.sunu_visa_number') }}</label>
        <input type="text" id="visa_number" name="visa_number" class="form-control"
               value="{{ old('visa_number', $product->visa_number) }}"
               placeholder="2022-14/MEFP/SG/DGTCP/DA/SUNU-Vie">
        @error('visa_number')<div style="color:var(--danger-color, #c62828); font-size:12px;">{{ $message }}</div>@enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="category">{{ __('common.sunu_category') }}</label>
        <select id="category" name="category" class="form-control">
            <option value="">—</option>
            @foreach($categories as $c)
                <option value="{{ $c }}" @selected(old('category', $product->category) === $c)>{{ \App\Models\InsuranceProduct::categoryLabel($c) }}</option>
            @endforeach
        </select>
        @error('category')<div style="color:var(--danger-color, #c62828); font-size:12px;">{{ $message }}</div>@enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="default_premium_amount">{{ __('common.sunu_default_premium_amount') }}</label>
        <input type="number" step="1" min="0" id="default_premium_amount" name="default_premium_amount" class="form-control"
               value="{{ old('default_premium_amount', $product->default_premium_amount !== null ? (int) $product->default_premium_amount : '') }}">
        @error('default_premium_amount')<div style="color:var(--danger-color, #c62828); font-size:12px;">{{ $message }}</div>@enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="policy_cost_amount">{{ __('common.sunu_policy_cost_amount') }}</label>
        <input type="number" step="1" min="0" id="policy_cost_amount" name="policy_cost_amount" class="form-control"
               value="{{ old('policy_cost_amount', $product->policy_cost_amount !== null ? (int) $product->policy_cost_amount : '') }}">
        @error('policy_cost_amount')<div style="color:var(--danger-color, #c62828); font-size:12px;">{{ $message }}</div>@enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="default_premium_frequency">{{ __('common.sunu_default_frequency') }}</label>
        <select id="default_premium_frequency" name="default_premium_frequency" class="form-control">
            @foreach($frequencies as $f)
                <option value="{{ $f }}" @selected(old('default_premium_frequency', $product->default_premium_frequency) === $f)>{{ \App\Models\InsuranceProduct::frequencyLabel($f) }}</option>
            @endforeach
        </select>
        @error('default_premium_frequency')<div style="color:var(--danger-color, #c62828); font-size:12px;">{{ $message }}</div>@enderror
    </div>

    <div class="form-group">
        <label class="form-label" for="sunulife_product_code">{{ __('common.sunu_sunulife_code') }}</label>
        <input type="text" id="sunulife_product_code" name="sunulife_product_code" class="form-control"
               value="{{ old('sunulife_product_code', $product->sunulife_product_code) }}">
        @error('sunulife_product_code')<div style="color:var(--danger-color, #c62828); font-size:12px;">{{ $message }}</div>@enderror
    </div>
</div>

<div class="form-group">
    <label class="form-label" for="guarantees_description">{{ __('common.sunu_guarantees') }}</label>
    <textarea id="guarantees_description" name="guarantees_description" class="form-control" rows="8">{{ old('guarantees_description', $product->guarantees_description) }}</textarea>
    @error('guarantees_description')<div style="color:var(--danger-color, #c62828); font-size:12px;">{{ $message }}</div>@enderror
</div>

<div class="form-group">
    <div class="checkbox-group">
        <input type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $product->exists ? $product->is_active : true))>
        <label for="is_active" style="margin:0;">{{ __('common.sunu_active_on_pos') }}</label>
    </div>
</div>

<div class="form-group" style="display:flex; gap:12px;">
    <button type="submit" class="btn">{{ __('common.save') }}</button>
    <a href="{{ route('business.sunu.products.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
</div>
