@extends('layouts.app')

@section('title', __('common.audit_details'))
@section('page-title', __('common.audit_details'))

@section('content')
@php
    $user = auth()->user();
    $canViewAuditDetails = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-audit-details', $businessUnit->id));
@endphp

@if(!$canViewAuditDetails)
    <div class="card">
        <div style="text-align: center; padding: 40px; color: var(--error-text);">
            <p>{{ __('common.unauthorized_access') }}</p>
            <a href="{{ route('business.dashboard') }}" class="btn">{{ __('common.back_to_dashboard') }}</a>
        </div>
    </div>
@else
<div class="card">
    <div class="toolbar">
        <h1 class="text-accent">{{ __('common.audit_details') }}</h1>
        <a href="{{ route('audit-trail.index', request()->query()) }}" class="btn btn-secondary">{{ __('common.back') }}</a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div>
            <label class="section-label">{{ __('common.date_time') }}</label>
            <p class="detail-value">
                {{ $auditLog->created_at->format('Y-m-d H:i:s') }}
            </p>
        </div>

        <div>
            <label class="section-label">{{ __('common.causer') }}</label>
            <p class="detail-value">
                {{ $auditLog->causer ? $auditLog->causer->name : __('common.system') }}
                @if($auditLog->causer)
                    <br>
                    <small class="muted">{{ $auditLog->causer->email }}</small>
                @endif
            </p>
        </div>

        <div>
            <label class="section-label">{{ __('common.action') }}</label>
            <p style="color: var(--text-primary); font-size: 16px; margin-top: 5px; text-transform: capitalize;">
                {{ $auditLog->event ?? 'N/A' }}
            </p>
        </div>

        <div>
            <label class="section-label">{{ __('common.model_type') }}</label>
            <p class="detail-value">
                {{ $auditLog->model_display_name ?? 'N/A' }}
            </p>
        </div>

        @if($auditLog->subject_id)
        <div>
            <label class="section-label">{{ __('common.model_id') }}</label>
            <p class="detail-value">
                {{ $auditLog->subject_id }}
            </p>
        </div>
        @endif

        <div>
            <label class="section-label">{{ __('common.ip_address') }}</label>
            <p class="detail-value">
                {{ $auditLog->ip_address ?? '-' }}
            </p>
        </div>

        @if($auditLog->user_agent)
        <div>
            <label class="section-label">{{ __('common.user_agent') }}</label>
            <p class="detail-value">
                {{ $auditLog->user_agent }}
            </p>
        </div>
        @endif
    </div>

    <div style="margin-bottom: 30px;">
        <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.description') }}</h2>
        <div style="padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
            <p style="color: var(--text-primary); margin: 0;">{{ $auditLog->description }}</p>
        </div>
    </div>

    @if(count($changes) > 0)
    <div style="margin-bottom: 30px;">
        <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.field_changes') }}</h2>
        <div class="data-table">
            <table>
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th>{{ __('common.field') }}</th>
                        <th>{{ __('common.old_values') }}</th>
                        <th>{{ __('common.new_values') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($changes as $field => $change)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td>
                                <strong>{{ ucfirst(str_replace('_', ' ', $field)) }}</strong>
                            </td>
                            <td>
                                <span class="text-error">
                                    {{ $change['old'] === null ? 'NULL' : (is_array($change['old']) ? json_encode($change['old'], JSON_PRETTY_PRINT) : $change['old']) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-success">
                                    {{ $change['new'] === null ? 'NULL' : (is_array($change['new']) ? json_encode($change['new'], JSON_PRETTY_PRINT) : $change['new']) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div style="margin-bottom: 30px;">
        <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.field_changes') }}</h2>
        <div style="padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
            <p style="color: var(--text-muted); margin: 0;">{{ __('common.no_changes') }}</p>
        </div>
    </div>
    @endif

    @if($auditLog->properties)
    <div style="margin-bottom: 30px;">
        <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.full_properties') }}</h2>
        <div style="padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
            <pre style="color: var(--text-primary); font-size: 14px; margin: 0; white-space: pre-wrap;">{{ json_encode($auditLog->properties, JSON_PRETTY_PRINT) }}</pre>
        </div>
    </div>
    @endif
</div>
@endif
@endsection

