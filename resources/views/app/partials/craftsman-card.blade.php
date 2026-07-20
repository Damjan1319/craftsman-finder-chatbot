<article class="craftsman-card {{ $featured ? 'premium' : '' }}">
    <div class="craftsman-top">
        <div class="avatar" style="background: {{ $category->accentBackground() }}; color: {{ $category->accentColor() }};">
            {{ strtoupper(mb_substr($craftsman->name, 0, 1)) }}
        </div>
        <div class="craftsman-info">
            <h2>{{ $craftsman->name }}</h2>
            <div class="location">{{ $craftsman->city }}</div>
        </div>
        @if ($featured)
            <span class="badge-premium">Preporučeno</span>
        @endif
    </div>
    @if ($craftsman->bio)
        <p class="craftsman-bio">{{ $craftsman->bio }}</p>
    @endif
    <div class="actions">
        <a href="tel:{{ $craftsman->phone }}" class="btn btn-primary">
            Pozovi · {{ $craftsman->phone }}
        </a>
        @if ($craftsman->viber_id)
            <a href="viber://chat?number={{ urlencode($craftsman->viber_id) }}" class="btn btn-secondary">
                Viber poruka
            </a>
        @endif
    </div>
</article>
