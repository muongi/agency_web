@extends('layouts.app')

@section('title', __('common.cnss_dashboard'))
@section('page-title', __('common.cnss_dashboard'))

@section('content')
@php
    $user = auth()->user();
    $bu = $user->currentBusinessUnit;
    $canViewCnssMembers = $user->isSuperAdmin() || ($bu && $user->hasPermission('view-cnss-members', $bu->id));
    $currency = config('currency.default', 'CFA');
    $rankingCard = 'padding: 20px; background: var(--bg-tertiary); border-radius: 8px; border: 1px solid var(--border-color);';
@endphp

@if(!$canViewCnssMembers)
    <div class="card">
        <div style="text-align: center; padding: 40px; color: var(--error-text);">
            <p>{{ __('common.unauthorized_access') }}</p>
            <a href="{{ route('business.dashboard') }}" class="btn">{{ __('common.back_to_dashboard') }}</a>
        </div>
    </div>
@else
<div class="card mb-20">
    <div class="mb-20">
        <h1 style="color: var(--accent-color); margin: 0 0 8px 0;">{{ __('common.cnss_dashboard') }}</h1>
        <p style="color: var(--text-muted); margin: 0;">
            {{ __('common.cnss_dashboard_scope_note') }}
        </p>
        <p style="margin-top: 10px;">
            <a href="{{ route('business.cnss.members.index') }}">{{ __('common.cnss_members') }}</a>
            ·
            <a href="{{ route('business.cnss.contributions.index') }}">{{ __('common.cnss_all_contributions') }}</a>
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 32px;">
        <div style="padding: 20px; background: var(--bg-tertiary); border-radius: 8px; border: 1px solid var(--border-color);">
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px;">{{ __('common.cnss_collected_today') }}</div>
            <div style="font-size: 22px; font-weight: bold; color: var(--accent-color);">{{ number_format($totals['today'], 2) }} {{ $currency }}</div>
        </div>
        <div style="padding: 20px; background: var(--bg-tertiary); border-radius: 8px; border: 1px solid var(--border-color);">
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px;">{{ __('common.cnss_collected_this_month') }}</div>
            <div style="font-size: 22px; font-weight: bold; color: var(--accent-color);">{{ number_format($totals['month'], 2) }} {{ $currency }}</div>
        </div>
        <div style="padding: 20px; background: var(--bg-tertiary); border-radius: 8px; border: 1px solid var(--border-color);">
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px;">{{ __('common.cnss_collected_this_year') }}</div>
            <div style="font-size: 22px; font-weight: bold; color: var(--accent-color);">{{ number_format($totals['year'], 2) }} {{ $currency }}</div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
        <div style="{{ $rankingCard }}">
            <h2 style="color: var(--accent-color); margin: 0 0 12px 0; font-size: 1.1rem;">{{ __('common.cnss_top_contributors') }}</h2>
            @if($topContributors->isEmpty())
                <p style="color: var(--text-muted); margin: 0;">{{ __('common.cnss_no_contributors_ranked') }}</p>
            @else
                <div class="data-table">
                    <table>
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border-color);">
                                <th style="padding: 10px; text-align: left; color: var(--accent-color);">{{ __('common.cnss_member') }}</th>
                                <th style="padding: 10px; text-align: right; color: var(--accent-color);">{{ __('common.cnss_amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topContributors as $row)
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 10px;">
                                        @if($row->enrollment)
                                            @php $e = $row->enrollment; @endphp
                                            <div>{{ trim(($e->last_name ?? '').' '.($e->first_names ?? '')) ?: '—' }}</div>
                                            <div style="font-size: 12px; color: var(--text-muted);">
                                                <code style="background: var(--bg-tertiary); padding: 2px 4px;">{{ $e->voluntary_insured_number }}</code>
                                            </div>
                                            @if($user->isSuperAdmin() || ($bu && $user->hasPermission('view-cnss-member', $bu->id)))
                                                <a href="{{ route('business.cnss.members.show', $e->id) }}" class="btn" style="padding: 4px 10px; font-size: 11px; margin-top: 6px;">{{ __('common.view') }}</a>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td style="padding: 10px; text-align: right; font-weight: 600;">{{ number_format($row->total_amount, 2) }} {{ $currency }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div style="{{ $rankingCard }}">
            <h2 style="color: var(--accent-color); margin: 0 0 12px 0; font-size: 1.1rem;">{{ __('common.cnss_top_agents') }}</h2>
            @if($topAgents->isEmpty())
                <p style="color: var(--text-muted); margin: 0;">{{ __('common.cnss_no_agents_ranked') }}</p>
            @else
                <div class="data-table">
                    <table>
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border-color);">
                                <th style="padding: 10px; text-align: left; color: var(--accent-color);">{{ __('common.agent') }}</th>
                                <th style="padding: 10px; text-align: right; color: var(--accent-color);">{{ __('common.cnss_amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topAgents as $row)
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 10px;">{{ $row->agent?->full_name ?? '—' }}</td>
                                    <td style="padding: 10px; text-align: right; font-weight: 600;">{{ number_format($row->total_amount, 2) }} {{ $currency }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endif
@endsection
