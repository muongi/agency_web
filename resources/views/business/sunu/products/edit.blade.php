@extends('layouts.app')

@section('title', __('common.sunu_product_edit_title'))
@section('page-title', __('common.sunu_product_edit'))

@section('content')
<div class="card" style="max-width:960px;">
    <h1 style="color:var(--accent-color);">{{ $product->code }}</h1>
    <form method="POST" action="{{ route('business.sunu.products.update', $product->id) }}">
        @include('business.sunu.products._form', ['method' => 'PUT'])
    </form>
</div>
@endsection
