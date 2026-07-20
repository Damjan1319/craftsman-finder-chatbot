@extends('layouts.app')

@section('title', 'O nama')
@section('heading', 'O nama')
@section('subheading', 'Kontakt i informacije')

@section('content')
    <div class="about-card">
        {!! nl2br(e($aboutText)) !!}
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
