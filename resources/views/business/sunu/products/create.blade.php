@extends('layouts.app')

@section('title', __('common.sunu_product_new_title'))
@section('page-title', __('common.sunu_product_new'))

@section('content')
<div class="card" style="max-width:960px;">
    <h1 style="color:var(--accent-color);">{{ __('common.sunu_product_add') }}</h1>
    <form method="POST" action="{{ route('business.sunu.products.store') }}">
        @include('business.sunu.products._form', ['method' => 'POST'])
    </form>
</div>
@endsection
