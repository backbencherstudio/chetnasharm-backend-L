<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Coming Soon</title>
    <link
        rel="icon"
        type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%236d28d9'/%3E%3Ctext x='50%25' y='54%25' dominant-baseline='middle' text-anchor='middle' font-family='Arial,sans-serif' font-weight='700' font-size='12' fill='%23ffffff'%3ELA%3C/text%3E%3C/svg%3E"
    >
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-brand-soft font-sans text-slate-900 antialiased">
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-6 py-16">
        <div class="pointer-events-none absolute -left-24 top-16 size-72 rounded-full bg-violet-200/50 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-16 bottom-10 size-80 rounded-full bg-fuchsia-200/40 blur-3xl"></div>

        <main class="relative z-10 mx-auto w-full max-w-3xl text-center">
            <img
                src="{{ asset('assets/img/logo/logo.webp') }}"
                alt="{{ config('app.name') }}"
                class="mx-auto h-12 w-auto sm:h-14"
            >

            <div class="mt-8 inline-flex items-center gap-2 rounded-full border border-violet-200 bg-white px-4 py-2 text-sm font-medium text-brand-muted shadow-sm">
                <span class="size-2 animate-pulse rounded-full bg-brand"></span>
                Under construction
            </div>

            <h1 class="mt-8 text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl md:text-6xl">
                Something better is
                <span class="text-brand">on the way</span>
            </h1>

            <p class="mx-auto mt-5 max-w-xl text-base leading-relaxed text-slate-600 sm:text-lg">
                We’re polishing a fresh design and improved features. Thanks for your patience while we get ready to launch.
            </p>

            <div class="mt-12 flex flex-col items-stretch justify-center gap-4 sm:flex-row sm:items-center">
                <div class="rounded-2xl border border-violet-100 bg-white px-5 py-4 text-left shadow-sm sm:min-w-40">
                    <p class="text-sm font-semibold text-slate-900">Fresh design</p>
                    <p class="mt-1 text-sm text-slate-500">Cleaner look and feel</p>
                </div>
                <div class="rounded-2xl border border-violet-100 bg-white px-5 py-4 text-left shadow-sm sm:min-w-40">
                    <p class="text-sm font-semibold text-slate-900">Better features</p>
                    <p class="mt-1 text-sm text-slate-500">Smoother learning flow</p>
                </div>
                <div class="rounded-2xl border border-violet-100 bg-white px-5 py-4 text-left shadow-sm sm:min-w-40">
                    <p class="text-sm font-semibold text-slate-900">Launching soon</p>
                    <p class="mt-1 text-sm text-slate-500">Almost ready for you</p>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
