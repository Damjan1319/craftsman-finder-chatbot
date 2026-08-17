<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1e3a5f">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Nađi majstora">
    <meta name="description" content="Nađi majstora — provereni majstori u vašem gradu.">

    <title>@yield('title', 'Nađi majstora')</title>

    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="icon" href="{{ asset('images/logo-icon.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('images/logo-icon.png') }}">
    <link rel="preload" href="{{ asset('images/logo.webp') }}" as="image" type="image/webp">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=6">
    @stack('head')
</head>
<body>
    <div id="page-loader" class="page-loader" aria-hidden="true">
        <div class="page-loader-inner">
            <img src="{{ asset('images/logo-icon.png') }}" alt="" width="40" height="40" class="page-loader-logo">
            <span>Učitavanje...</span>
        </div>
    </div>
    <div id="install-banner" class="install-banner">
        <span>Instaliraj aplikaciju na telefon</span>
        <div class="install-banner-actions">
            <button type="button" id="install-btn">Instaliraj</button>
            <button type="button" class="dismiss" id="install-dismiss" aria-label="Zatvori">&times;</button>
        </div>
    </div>

    <div class="shell">
        <aside class="sidebar" aria-label="Navigacija">
            <div class="sidebar-brand">
                <a href="{{ route('app.home') }}" class="brand-link">
                    <img
                        src="{{ asset('images/logo.webp') }}"
                        alt="Nađi majstora"
                        class="brand-logo"
                        width="160"
                        height="160"
                        decoding="async"
                    >
                </a>
            </div>

            <nav class="sidebar-nav">
                <a href="{{ route('app.home') }}" class="sidebar-link {{ request()->routeIs('app.home') || request()->routeIs('app.category') || request()->routeIs('app.search') ? 'active' : '' }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    Traži majstora
                </a>
                <a href="{{ route('app.about') }}" class="sidebar-link {{ request()->routeIs('app.about') ? 'active' : '' }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                    O nama
                </a>
            </nav>
        </aside>

        <div class="content-area">
            <header class="page-header">
                <div class="page-header-inner">
                    <div class="page-header-top">
                        @hasSection('back')
                            <a href="@yield('back')" class="header-back" aria-label="Nazad">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
                            </a>
                        @endif
                        <div class="page-titles">
                            <h1>@yield('heading', 'Nađi majstora')</h1>
                            @hasSection('subheading')
                                <p>@yield('subheading')</p>
                            @endif
                        </div>
                    </div>
                    @hasSection('breadcrumbs')
                        <div class="breadcrumbs">@yield('breadcrumbs')</div>
                    @endif
                </div>
            </header>

            <main class="main">
                @if (session('info'))
                    <div class="alert">{{ session('info') }}</div>
                @endif

                <div class="page-content">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    <nav class="bottom-nav" aria-label="Glavna navigacija">
        <a href="{{ route('app.home') }}" class="nav-item {{ request()->routeIs('app.home') || request()->routeIs('app.category') || request()->routeIs('app.search') ? 'active' : '' }}">
            <span class="icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></span>
            Traži
        </a>
        <a href="{{ route('app.about') }}" class="nav-item {{ request()->routeIs('app.about') ? 'active' : '' }}">
            <span class="icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg></span>
            O nama
        </a>
    </nav>

    <script>
        (() => {
            const loader = document.getElementById('page-loader');
            const showLoader = () => loader?.classList.add('show');
            const hideLoader = () => loader?.classList.remove('show');

            document.addEventListener('click', (event) => {
                const link = event.target.closest('a[href]');

                if (!link || link.target === '_blank' || link.origin !== location.origin) {
                    return;
                }

                if (link.pathname === location.pathname) {
                    return;
                }

                showLoader();
                link.classList.add('is-loading');
            });

            window.addEventListener('pageshow', hideLoader);
            window.addEventListener('load', hideLoader);

            document.querySelectorAll('a[href^="/"]').forEach((link) => {
                if (link.origin === location.origin) {
                    link.dataset.prefetch = 'true';
                }
            });

            let prefetchTimer;
            document.addEventListener('mouseover', (event) => {
                const link = event.target.closest('a[data-prefetch]');

                if (!link) {
                    return;
                }

                clearTimeout(prefetchTimer);
                prefetchTimer = setTimeout(() => {
                    if (document.querySelector(`link[rel="prefetch"][href="${link.href}"]`)) {
                        return;
                    }

                    const hint = document.createElement('link');
                    hint.rel = 'prefetch';
                    hint.href = link.href;
                    document.head.appendChild(hint);
                }, 80);
            });

            window.addEventListener('load', () => {
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.register('{{ asset('sw.js') }}?v=6').catch(() => {});
                }
            }, { once: true });
        })();

        let deferredPrompt;
        const banner = document.getElementById('install-banner');
        const installBtn = document.getElementById('install-btn');
        const dismissBtn = document.getElementById('install-dismiss');

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            if (!localStorage.getItem('pwa-dismissed')) {
                banner.classList.add('show');
            }
        });

        installBtn?.addEventListener('click', async () => {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            await deferredPrompt.userChoice;
            deferredPrompt = null;
            banner.classList.remove('show');
        });

        dismissBtn?.addEventListener('click', () => {
            banner.classList.remove('show');
            localStorage.setItem('pwa-dismissed', '1');
        });
    </script>
    @stack('scripts')
</body>
</html>
