@extends('layouts.app')

@section('title', __('common.sunu_products_title'))
@section('page-title', __('common.sunu_products_catalogue'))

@section('content')
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap;">
        <h1 style="color:var(--accent-color); margin:0;">{{ __('common.sunu_products_heading') }}</h1>
        @if(auth()->user()->hasPermission('manage-sunu-products'))
        <a href="{{ route('business.sunu.products.create') }}" class="btn">{{ __('common.sunu_product_add') }}</a>
        @endif
    </div>

    <form method="GET" action="{{ route('business.sunu.products.index') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin:20px 0;">
        <div class="form-group" style="margin:0;">
            <label class="form-label" for="q">{{ __('common.search') }}</label>
            <input type="text" id="q" name="q" class="form-control" value="{{ request('q') }}" placeholder="{{ __('common.sunu_search_products_placeholder') }}">
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label" for="category">{{ __('common.sunu_category') }}</label>
            <select id="category" name="category" class="form-control">
                <option value="">{{ __('common.sunu_all_categories') }}</option>
                @foreach($categories as $c)
                    <option value="{{ $c }}" @selected(request('category') === $c)>{{ \App\Models\InsuranceProduct::categoryLabel($c) }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label" for="status">{{ __('common.status') }}</label>
            <select id="status" name="status" class="form-control">
                <option value="">{{ __('common.sunu_all') }}</option>
                <option value="active" @selected(request('status') === 'active')>{{ __('common.active') }}</option>
                <option value="inactive" @selected(request('status') === 'inactive')>{{ __('common.inactive') }}</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <button type="submit" class="btn btn-sm">{{ __('common.filter') }}</button>
            <a href="{{ route('business.sunu.products.index') }}" class="btn btn-secondary btn-sm">{{ __('common.sunu_reset') }}</a>
        </div>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>{{ __('common.sunu_order') }}</th>
                <th>{{ __('common.sunu_code') }}</th>
                <th>{{ __('common.sunu_product') }}</th>
                <th>{{ __('common.sunu_visa_number') }}</th>
                <th>{{ __('common.sunu_category') }}</th>
                <th>{{ __('common.sunu_default_premium') }}</th>
                <th>{{ __('common.sunu_policy_fees') }}</th>
                <th>{{ __('common.active') }}</th>
                <th>{{ __('common.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $p)
                <tr>
                    <td>{{ $p->display_order }}</td>
                    <td><strong>{{ $p->code }}</strong></td>
                    <td>
                        {{ $p->localizedName() }}
                        @if($p->guarantees_description)
                            <div style="color:var(--text-muted); font-size:12px; margin-top:4px;">
                                {{ Str::limit($p->guarantees_description, 140) }}
                            </div>
                        @endif
                    </td>
                    <td style="font-size:12px;">{{ $p->visa_number ?: '—' }}</td>
                    <td>{{ \App\Models\InsuranceProduct::categoryLabel($p->category) }}</td>
                    <td>{{ $p->default_premium_amount !== null ? number_format((float) $p->default_premium_amount, 0, ',', ' ').' XOF' : '—' }}</td>
                    <td>{{ $p->policy_cost_amount !== null ? number_format((float) $p->policy_cost_amount, 0, ',', ' ').' XOF' : '—' }}</td>
                    <td>{{ $p->is_active ? __('common.sunu_yes') : __('common.sunu_no') }}</td>
                    <td>
                        @if(auth()->user()->hasPermission('manage-sunu-products'))
                        <a href="{{ route('business.sunu.products.edit', $p->id) }}" class="btn btn-sm">{{ __('common.edit') }}</a>
                        <form method="POST" action="{{ route('business.sunu.products.toggle', $p->id) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm">{{ $p->is_active ? __('common.sunu_deactivate') : __('common.activate') }}</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9">{{ __('common.sunu_no_products') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <p style="color:var(--text-muted); font-size:12px; margin-top:16px;">
        {{ __('common.sunu_products_hint') }}
    </p>
</div>
@endsection
