@extends('layouts.app')

@section('title', __('common.cnss_member_detail'))
@section('page-title', __('common.cnss_member_detail'))

@section('content')
@php
    $user = auth()->user();
    $bu = $user->currentBusinessUnit;
    $canViewMember = $user->isSuperAdmin() || ($bu && $user->hasPermission('view-cnss-member', $bu->id));
@endphp

@if(!$canViewMember)
    <div class="card">
        <div style="text-align: center; padding: 40px; color: var(--error-text);">
            <p>{{ __('common.unauthorized_access') }}</p>
            <a href="{{ route('business.dashboard') }}" class="btn">{{ __('common.back_to_dashboard') }}</a>
        </div>
    </div>
@else
<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
        <div>
            <h1 style="color: var(--accent-color); margin-bottom: 8px;">{{ __('common.cnss_member_detail') }}</h1>
            <p style="color: var(--text-muted); margin: 0;">
                <a href="{{ route('business.cnss.members.index') }}">{{ __('common.cnss_members') }}</a>
            </p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            @if(!empty($canEdit))
                <a href="{{ route('business.cnss.members.edit', $enrollment->id) }}" class="btn btn-secondary">{{ __('common.edit') }}</a>
            @endif
            @if(!empty($canDelete))
                <form action="{{ route('business.cnss.members.destroy', $enrollment->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('{{ __('common.confirm_delete') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">{{ __('common.delete') }}</button>
                </form>
            @endif
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div>
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">{{ __('common.cnss_voluntary_insured_number') }}</div>
            <div><code style="background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">{{ $enrollment->voluntary_insured_number }}</code></div>
        </div>
        <div>
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">{{ __('common.cnss_affiliation_number') }}</div>
            <div><code style="background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">{{ $enrollment->cnss_registration_affiliation_number }}</code></div>
        </div>
        @if($enrollment->worker_number)
            <div>
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">{{ __('common.cnss_worker_number') }}</div>
                <div><code style="background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">{{ $enrollment->worker_number }}</code></div>
            </div>
        @endif
        <div>
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">{{ __('common.status') }}</div>
            <div>{{ $enrollment->status }}</div>
        </div>
        <div>
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">{{ __('common.last_name') }}</div>
            <div>{{ $enrollment->last_name ?? '—' }}</div>
        </div>
        <div>
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">{{ __('common.first_names') }}</div>
            <div>{{ $enrollment->first_names ?? '—' }}</div>
        </div>
        <div>
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">{{ __('common.phone') }}</div>
            <div>{{ $enrollment->phone ?? '—' }}</div>
        </div>
        <div>
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">{{ __('common.date') }}</div>
            <div>{{ $enrollment->effective_date?->format('Y-m-d') ?? '—' }} / {{ $enrollment->registration_date?->format('Y-m-d') ?? '—' }}</div>
        </div>
        @if($enrollment->agent)
            <div>
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">{{ __('common.agent') }}</div>
                <div>{{ $enrollment->agent->full_name }}</div>
            </div>
        @endif
        @if($enrollment->terminal)
            <div>
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">{{ __('common.terminal_serial') }}</div>
                <div>{{ $enrollment->terminal->serial_number }}</div>
            </div>
        @endif
    </div>

    @if($enrollment->emergency_contact_name || $enrollment->emergency_contact_phone)
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
            @if($enrollment->emergency_contact_name)
                <div>
                    <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">{{ __('common.cnss_emergency_contact_name') }}</div>
                    <div>{{ $enrollment->emergency_contact_name }}</div>
                </div>
            @endif
            @if($enrollment->emergency_contact_phone)
                <div>
                    <div>{{ __('common.cnss_emergency_contact_phone') }}</div>
                    <div>{{ $enrollment->emergency_contact_phone }}</div>
                </div>
            @endif
        </div>
    @endif

    <div style="margin-bottom: 24px;">
        <h2 style="color: var(--accent-color); margin: 0 0 16px 0; font-size: 1.1rem;">{{ __('common.cnss_enrollment_documents') }}</h2>
        @if($enrollment->id_document_path || $enrollment->id_document_back_path || $enrollment->birth_certificate_path)
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                @if($enrollment->id_document_path)
                    <div>
                        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px;">{{ __('common.cnss_id_document_front') }}</div>
                        <img
                            src="{{ route('business.cnss.members.document', [$enrollment->id, 'id_document']) }}"
                            alt="{{ __('common.cnss_id_document_front') }}"
                            style="max-width: 100%; max-height: 320px; border-radius: 8px; border: 1px solid var(--border-color); object-fit: contain; background: var(--bg-primary);"
                        >
                    </div>
                @endif
                @if($enrollment->id_document_back_path)
                    <div>
                        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px;">{{ __('common.cnss_id_document_back') }}</div>
                        <img
                            src="{{ route('business.cnss.members.document', [$enrollment->id, 'id_document_back']) }}"
                            alt="{{ __('common.cnss_id_document_back') }}"
                            style="max-width: 100%; max-height: 320px; border-radius: 8px; border: 1px solid var(--border-color); object-fit: contain; background: var(--bg-primary);"
                        >
                    </div>
                @endif
                @if($enrollment->birth_certificate_path)
                    <div>
                        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px;">{{ __('common.cnss_birth_certificate') }}</div>
                        <img
                            src="{{ route('business.cnss.members.document', [$enrollment->id, 'birth_certificate']) }}"
                            alt="{{ __('common.cnss_birth_certificate') }}"
                            style="max-width: 100%; max-height: 320px; border-radius: 8px; border: 1px solid var(--border-color); object-fit: contain; background: var(--bg-primary);"
                        >
                    </div>
                @endif
            </div>
        @else
            <p style="color: var(--text-muted); margin: 0;">{{ __('common.cnss_no_documents') }}</p>
        @endif
    </div>

    @if(!empty($enrollment->form_data))
        <details style="margin-bottom: 24px;">
            <summary style="cursor: pointer; color: var(--accent-color); font-weight: 600;">{{ __('common.cnss_form_data') }}</summary>
            <pre style="margin-top: 12px; padding: 12px; background: var(--bg-tertiary); border-radius: 8px; overflow-x: auto; font-size: 12px;">{{ json_encode($enrollment->form_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </details>
    @endif
</div>

<div class="card">
    <h2 style="color: var(--accent-color); margin-bottom: 16px;">{{ __('common.cnss_contributions') }}</h2>

    @if($enrollment->contributions->isEmpty())
        <p style="color: var(--text-muted);">{{ __('common.cnss_no_contributions') }}</p>
    @else
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.cnss_mode') }}</th>
                        <th style="padding: 12px; text-align: right; color: var(--accent-color);">{{ __('common.cnss_amount') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.cnss_reference_period') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.cnss_payment_frequency') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.transaction_reference') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.created_at') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($enrollment->contributions as $c)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 12px;">{{ $c->mode }}</td>
                            <td style="padding: 12px; text-align: right; font-weight: bold;">{{ number_format((float) $c->amount, 2) }}</td>
                            <td style="padding: 12px;">{{ $c->reference_period }}</td>
                            <td style="padding: 12px;">{{ $c->payment_frequency }}</td>
                            <td style="padding: 12px;">{{ $c->reference ?? '—' }}</td>
                            <td style="padding: 12px;">{{ $c->created_at?->format('Y-m-d H:i') }}</td>
                            <td style="padding: 12px;">
                                @if($canViewTransactions && $c->transaction_id)
                                    <a href="{{ route('transactions.show', $c->transaction_id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.view_transaction') }}</a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endif
@endsection
