@extends('layouts.app')

@section('title', __('common.edit_client_group'))
@section('page-title', __('common.edit_client_group'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.edit_client_group') }}</h1>
    
    <form method="POST" action="{{ route('clients.groups.update', $group->id) }}">
        @csrf
        @method('PUT')

        <div class="detail-grid">
            <div class="form-group">
                <label class="form-label" for="name">{{ __('common.group_name') }} <span class="text-error">*</span></label>
                <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $group->name) }}" required autofocus placeholder="e.g., VIP Clients, Regular Customers">
                @error('name')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group detail-full">
                <label class="form-label" for="description">{{ __('common.group_description') }}</label>
                <textarea id="description" name="description" class="form-control" rows="3" placeholder="{{ __('common.optional_description') }}">{{ old('description', $group->description) }}</textarea>
                @error('description')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>

        @if($clients->count() > 0)
        <div class="form-group" style="margin-top: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <label class="form-label" style="font-size: 18px; margin: 0;">{{ __('common.select_group_members') }}</label>
                <div class="btn-row">
                    <button type="button" class="btn btn-secondary" style="padding: 5px 15px; font-size: 12px;" onclick="selectAllClients()">{{ __('common.select_all') }}</button>
                    <button type="button" class="btn btn-secondary" style="padding: 5px 15px; font-size: 12px;" onclick="deselectAllClients()">{{ __('common.deselect_all') }}</button>
                </div>
            </div>
            <div style="max-height: 400px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 4px; padding: 15px; background: var(--bg-primary);">
                <input type="text" id="client-search" class="form-control" style="margin-bottom: 15px;" placeholder="{{ __('common.search_clients') }}" onkeyup="filterClients()">
                <div id="clients-list">
                    @foreach($clients as $client)
                        <div class="client-item" style="padding: 8px; border-bottom: 1px solid var(--border-color);">
                            <label style="display: flex; align-items: center; cursor: pointer; margin: 0;">
                                <input type="checkbox" name="client_ids[]" value="{{ $client->id }}" class="client-checkbox" data-name="{{ strtolower($client->full_name) }}" data-phone="{{ $client->phone_number ?? '' }}" {{ in_array($client->id, old('client_ids', $selectedClientIds)) ? 'checked' : '' }} style="margin-right: 10px;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: var(--text-primary);">{{ $client->full_name }}</div>
                                    @if($client->phone_number)
                                        <div style="font-size: 12px; color: var(--text-muted);">{{ $client->phone_number }}</div>
                                    @endif
                                </div>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
            @error('client_ids')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>
        @else
        <div style="padding: 20px; background: var(--bg-tertiary); border-radius: 4px; margin-top: 20px; text-align: center; color: var(--text-muted);">
            <p>{{ __('common.no_clients_available') }}</p>
            <a href="{{ route('clients.create') }}" class="btn" style="margin-top: 10px;">{{ __('common.add_client') }}</a>
        </div>
        @endif

        <div class="btn-row mt-24">
            <button type="submit" class="btn">{{ __('common.update') }}</button>
            <a href="{{ route('clients.groups.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>

<script>
function filterClients() {
    const search = document.getElementById('client-search').value.toLowerCase();
    const items = document.querySelectorAll('.client-item');
    
    items.forEach(item => {
        const checkbox = item.querySelector('.client-checkbox');
        const name = checkbox.getAttribute('data-name');
        const phone = checkbox.getAttribute('data-phone') || '';
        
        if (name.includes(search) || phone.includes(search)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

function selectAllClients() {
    const checkboxes = document.querySelectorAll('.client-checkbox');
    checkboxes.forEach(cb => {
        if (cb.closest('.client-item').style.display !== 'none') {
            cb.checked = true;
        }
    });
}

function deselectAllClients() {
    const checkboxes = document.querySelectorAll('.client-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = false;
    });
}
</script>
@endsection
