@extends('layouts.app')

@section('title', 'O nama')
@section('heading', 'O nama')
@section('subheading', 'Kontakt i informacije')

@section('content')
    <div class="about-card">
        {!! nl2br(e($aboutText)) !!}
    </div>

    <div class="about-card" style="margin-top: 1rem;">
        <p class="section-label">Prijava majstora</p>
        <p>Ukoliko ste majstor i želite da se prijavite na platformu Nađi majstora, kontaktirajte nas na mejl:</p>
        <a href="mailto:{{ $craftsmanEmail }}" class="btn btn-outline" style="margin-top: 0.75rem;">
            {{ $craftsmanEmail }}
        </a>
    </div>

    @if ($contactPhone || $contactEmail)
        <div class="contact-section">
            <p class="section-label">Kontakt</p>
            <div class="contact-list">
                @if ($contactPhone)
                    <a href="tel:{{ $contactPhone }}" class="btn btn-primary">
                        {{ $contactPhone }}
                    </a>
                @endif
                @if ($contactEmail)
                    <a href="mailto:{{ $contactEmail }}" class="btn btn-outline">
                        {{ $contactEmail }}
                    </a>
                @endif
            </div>
        </div>
    @endif
@endsection
