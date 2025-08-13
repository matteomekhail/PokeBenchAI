<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>PokeBenchAI</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="antialiased pk-bg min-h-screen">
        <header class="sticky top-0 z-20 bg-[#101010]/90 backdrop-blur border-b border-[#000] shadow-[0_2px_0_#000]">
            <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
                <a href="/" class="pk-title flex items-center gap-2">
                    <img src="/assets/ui/pokeball.svg" alt="pokeball" class="h-6 w-6" width="24" height="24"/>
                    PokeBenchAI
                </a>
                <nav class="flex items-center gap-2">
                    <a href="/" class="pk-btn">Home</a>
                    <!-- removed explicit Gen1 link; benchmarks navigable from Home -->

                </nav>
            </div>
            <div class="pk-accent-red-line"></div>
        </header>
        <main class="mx-auto p-4 max-w-[1100px]">
            @inertia
        </main>
    </body>
    </html>

