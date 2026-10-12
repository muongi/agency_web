@extends('layouts.app')

@section('title', __('common.audit_trail'))
@section('page-title', __('common.audit_trail'))

@section('content')
@php
    $user = auth()->user();
    $canViewAuditTrail = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-audit-trail', $businessUnit->id));
@endphp

@if(!$canViewAuditTrail)
    <div class="card">
        <div style="text-align: center; padding: 40px; color: var(--error-text);">
            <p>{{ __('common.unauthorized_access') }}</p>
            <a href="{{ route('business.dashboard') }}" class="btn">{{ __('common.back_to_dashboard') }}</a>
        </div>
    </div>
@else
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <h1 class="text-accent">{{ __('common.audit_trail') }}</h1>
        @if($user->isSuperAdmin() || ($businessUnit && $user->hasPermission('export-audit-trail', $businessUnit->id)))
            <a href="{{ route('audit-trail.export', request()->all()) }}" class="btn btn-secondary">{{ __('common.export_audit_logs') }}</a>
        @endif
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('audit-trail.index') }}" style="margin-bottom: 20px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
            <div class="form-group">
                <label class="form-label">{{ __('common.start_date') }}</label>
                <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.end_date') }}</label>
                <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.user') }}</label>
                <select name="user_id" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                            {{ $u->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.model_type') }}</label>
                <select name="model_type" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach($modelTypes as $type)
                        <option value="{{ $type['value'] }}" {{ request('model_type') == $type['value'] ? 'selected' : '' }}>
                            {{ $type['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.event_type') }}</label>
                <select name="event" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    <option value="created" {{ request('event') == 'created' ? 'selected' : '' }}>{{ __('common.created') }}</option>
                    <option value="updated" {{ request('event') == 'updated' ? 'selected' : '' }}>{{ __('common.updated') }}</option>
                    <option value="deleted" {{ request('event') == 'deleted' ? 'selected' : '' }}>{{ __('common.deleted') }}</option>
                </select>
            </div>
        </div>
        <div class="btn-row">
            <input type="text" name="search" class="form-control" placeholder="{{ __('common.search') }}..." value="{{ request('search') }}" style="flex: 1;">
            <button type="submit" class="btn">{{ __('common.filter') }}</button>
            <a href="{{ route('audit-trail.index') }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
        </div>
    </form>

    @if($auditLogs->count() > 0)
        <div class="data-table">
            <table>
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th>{{ __('common.date_time') }}</th>
                        <th>{{ __('common.user') }}</th>
                        <th>{{ __('common.action') }}</th>
                        <th>{{ __('common.model_type') }}</th>
                        <th>{{ __('common.description') }}</th>
                        <th>{{ __('common.ip_address') }}</th>
                        <th>{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($auditLogs as $log)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td>
                                {{ $log->created_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td>
                                {{ $log->causer ? $log->causer->name : __('common.system') }}
                            </td>
                            <td>
                                <span style="text-transform: capitalize;">{{ $log->event ?? 'N/A' }}</span>
                            </td>
                            <td>
                                {{ $log->model_display_name ?? 'N/A' }}
                                @if($log->subject_id)
                                    <br>
                                    <small class="muted">ID: {{ $log->subject_id }}</small>
                                @endif
                            </td>
                            <td>
                                {{ \Illuminate\Support\Str::limit($log->description, 50) }}
                            </td>
                            <td>
                                {{ $log->ip_address ?? '-' }}
                            </td>
                            <td>
                                <a href="{{ route('audit-trail.show', $log->id) }}" class="btn btn-sm">{{ __('common.view') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-20">
            {{ $auditLogs->links() }}
        </div>
    @else
        <div class="muted-center">
            <p class="mb-20">{{ __('common.no_audit_logs') }}</p>
        </div>
    @endif
</div>
@endif
@endsection

