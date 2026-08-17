@extends('layouts.app')

@section('title', 'Početna')
@section('heading', 'Nađi majstora')
@section('subheading', 'Provereni majstori u vašem gradu')

@section('content')
    <div class="hero hero-home">
        <img
            src="{{ asset('images/logo.webp') }}"
            alt="Nađi majstora"
            class="hero-logo"
            width="220"
            height="220"
            decoding="async"
            fetchpriority="high"
        >
        <h2>Koji majstor vam treba?</h2>
        <p>Izaberite kategoriju, zatim grad — odmah dobijate kontakt proverenog majstora.</p>
    </div>

    @if ($categories->isEmpty())
        <div class="empty">
            <div class="empty-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77A6 6 0 0 1 21 12v.5"/><path d="M9.3 17.7a1 1 0 0 0 0-1.4l-1.6-1.6a1 1 0 0 0-1.4 0L2.5 18.1A6 6 0 0 1 3 11.5V11"/><path d="M8 12h8"/><path d="M12 8v8"/></svg>
            </div>
            <h3>Nema dostupnih kategorija</h3>
            <p>Trenutno nema aktivnih majstora.<br>Proverite ponovo uskoro.</p>
        </div>
    @else
        <p class="section-label">Kategorije</p>
        <div class="grid">
            @foreach ($categories as $category)
                <a href="{{ route('app.category', $category) }}" class="category-card">
                    <div class="category-icon" style="background: {{ $category->accentBackground() }}; color: {{ $category->accentColor() }};">
                        {{ $category->initials() }}
                    </div>
                    <div class="category-info">
                        <strong>{{ $category->name }}</strong>
                        <span class="meta">{{ $category->activeCraftsmenLabel() }}</span>
                    </div>
                    <span class="card-arrow" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
@endsection
