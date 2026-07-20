@extends('layouts.app')

@section('title', $category->name.' — '.$city)
@section('heading', $category->name)
@section('subheading', $city)
@section('back', route('app.category', $category))

@section('breadcrumbs')
    {{ $category->name }} <span class="sep">/</span> <span class="current">{{ $city }}</span>
@endsection

@section('content')
    <p class="results-count">{{ $craftsmen->count() }} {{ $craftsmen->count() === 1 ? 'majstor pronađen' : 'majstora pronađeno' }}</p>

    @if ($recommended->isNotEmpty())
        <p class="section-label section-label-featured">Preporučeni majstori</p>
        <div class="grid grid-craftsmen">
            @foreach ($recommended as $craftsman)
                @include('app.partials.craftsman-card', ['craftsman' => $craftsman, 'category' => $category, 'featured' => true])
            @endforeach
        </div>
    @endif

    @if ($others->isNotEmpty())
        @if ($recommended->isNotEmpty())
            <p class="section-label">Ostali majstori</p>
        @endif
        <div class="grid grid-craftsmen">
            @foreach ($others as $craftsman)
                @include('app.partials.craftsman-card', ['craftsman' => $craftsman, 'category' => $category, 'featured' => false])
            @endforeach
        </div>
    @endif
@endsection
