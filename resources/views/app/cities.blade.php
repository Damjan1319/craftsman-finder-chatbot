@extends('layouts.app')

@section('title', $category->name)
@section('heading', $category->name)
@section('subheading', 'Izaberite grad')
@section('back', route('app.home'))

@section('breadcrumbs')
    <span class="current">{{ $category->name }}</span>
@endsection

@section('content')
    <p class="section-label">Gradovi</p>
    <div class="grid">
        @foreach ($cities as $city)
            <a href="{{ route('app.search', ['category' => $category, 'city' => $city->city]) }}" class="city-card">
                <div class="city-marker" style="background: {{ $category->accentBackground() }}; color: {{ $category->accentColor() }};">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
                <div class="city-info">
                    <strong>{{ $city->city }}</strong>
                    <div class="meta">{{ $city->craftsmen_count }} {{ $city->craftsmen_count === 1 ? 'majstor' : 'majstora' }}</div>
                </div>
                <span class="card-arrow" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                </span>
            </a>
        @endforeach
    </div>
@endsection
