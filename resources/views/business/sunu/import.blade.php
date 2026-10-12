@extends('layouts.app')

@section('title', __('common.sunu_import_title'))
@section('page-title', __('common.sunu_import_page_title'))

@section('content')
<div class="card">
    <h1 style="color:var(--accent-color);">{{ __('common.sunu_import_heading') }}</h1>
    <p>{{ __('common.sunu_import_columns', ['columns' => implode(', ', \App\Http\Controllers\SunuWebController::IMPORT_COLUMNS)]) }}</p>
    <p class="muted" style="font-size: 13px;">{{ __('common.sunu_import_sample_help') }}</p>
    <p><a href="{{ route('business.sunu.import.sample') }}" class="btn btn-secondary">{{ __('common.sunu_import_sample') }}</a></p>
    @if(session('import_errors'))
        <div class="alert alert-warning">
            <ul>@foreach(session('import_errors') as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="POST" action="{{ route('business.sunu.import.store') }}" enctype="multipart/form-data">
        @csrf
        <input type="file" name="file" accept=".csv,.txt" required class="form-input">
        <button class="btn" type="submit">{{ __('common.sunu_import_button') }}</button>
    </form>
</div>
@endsection
