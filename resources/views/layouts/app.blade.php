<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ?? (View::hasSection('page-title') ? View::yieldContent('page-title') : 'Painel') }} — meWork360</title>

    <!-- Google Fonts: Inter + Fira Code + Material Symbols -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fira+Code:wght@400;450;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen flex bg-background text-on-background font-sans text-[14px]">

{{-- ===== LEFT SIDEBAR ===== --}}
<aside class="fixed left-0 top-0 h-full w-64 z-50 flex flex-col bg-surface-container border-r border-outline-variant">

    {{-- Brand --}}
    <div class="px-md pt-lg pb-lg border-b border-outline-variant">
        <div class="flex items-center gap-sm mb-lg">
            <div class="w-9 h-9 rounded-lg bg-primary-container flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-on-primary-container icon-fill" style="font-size:20px">hub</span>
            </div>
            <div>
                <p class="font-semibold text-[15px] leading-tight text-on-surface">meWork360</p>
                <p class="text-[11px] font-mono text-on-surface-variant mt-0.5">Deployer API</p>
            </div>
        </div>
        <a href="{{ route('api-keys.index') }}"
           class="w-full bg-primary text-on-primary rounded py-[9px] px-md font-semibold text-[12px] tracking-wide uppercase hover:bg-primary-fixed transition-colors flex items-center justify-center gap-xs">
            <span class="material-symbols-outlined" style="font-size:16px">add</span>
            Nova Credencial
        </a>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 overflow-y-auto px-sm py-sm space-y-[2px]">
        @php
            $navItems = [
                ['icon' => 'dashboard',    'label' => 'Dashboard',              'route' => 'dashboard'],
                ['icon' => 'vpn_key',      'label' => 'Credenciais',            'route' => 'api-keys.index'],
                ['icon' => 'list_alt',     'label' => 'Logs de Requisição',     'route' => 'audit.index'],
                ['icon' => 'cloud_queue',  'label' => 'Logs de Provisionamento','route' => 'queue.index'],
                ['icon' => 'settings',     'label' => 'Configurações',          'route' => 'settings.index'],
            ];
        @endphp

        @foreach ($navItems as $item)
            @php
                $active = request()->routeIs($item['route'])
                    || ($item['route'] === 'settings.index' && request()->routeIs('cluster-servers.*'));
                $routeUrl = null;
                try { $routeUrl = route($item['route']); } catch (\Exception) {}
            @endphp
            @if ($routeUrl)
                <a href="{{ $routeUrl }}"
                   class="flex items-center gap-sm px-md py-[9px] rounded-lg font-semibold text-[12px] tracking-wide uppercase transition-all duration-150
                          {{ $active
                             ? 'bg-secondary-container text-on-secondary-container shadow-[inset_2px_0_0_0_#adc6ff]'
                             : 'text-on-surface-variant hover:bg-surface-variant hover:text-on-surface' }}">
                    <span class="material-symbols-outlined {{ $active ? 'icon-fill' : '' }}" style="font-size:20px">{{ $item['icon'] }}</span>
                    {{ $item['label'] }}
                </a>
            @endif
        @endforeach
    </nav>

    {{-- Footer: logout + profile --}}
    <div class="px-sm py-sm border-t border-outline-variant space-y-[2px]">
        @can('manage-operators')
        <a href="{{ route('operators.index') }}"
           class="flex items-center gap-sm px-md py-[9px] rounded-lg font-semibold text-[12px] tracking-wide uppercase text-on-surface-variant hover:bg-surface-variant hover:text-on-surface transition-all duration-150">
            <span class="material-symbols-outlined" style="font-size:18px">group</span>
            Operadores
        </a>
        @endcan
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit"
                    class="w-full flex items-center gap-sm px-md py-[9px] rounded-lg font-semibold text-[12px] tracking-wide uppercase text-on-surface-variant hover:bg-error/10 hover:text-error transition-all duration-150">
                <span class="material-symbols-outlined" style="font-size:18px">logout</span>
                Sair
            </button>
        </form>
    </div>
</aside>

{{-- ===== MAIN WRAPPER ===== --}}
<div class="flex-1 ml-64 flex flex-col min-h-screen">

    {{-- ===== TOPBAR ===== --}}
    <header class="fixed top-0 z-40 bg-surface border-b border-outline-variant h-16 flex items-center justify-between px-lg"
            style="left: 16rem; right: 0;">

        {{-- Left: page brand --}}
        <div class="flex items-center gap-md">
            <span class="font-bold text-[20px] tracking-tight text-primary">meWork360</span>
            {{-- Search --}}
            <div class="relative hidden md:block w-72">
                <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant" style="font-size:18px">search</span>
                <input type="text"
                       class="w-full bg-surface-container-highest border border-outline-variant rounded py-1.5 pl-9 pr-10 text-[13px] text-on-surface placeholder:text-on-surface-variant focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                       placeholder="Buscar recursos...">
                <kbd class="absolute right-sm top-1/2 -translate-y-1/2 font-mono text-[10px] text-on-surface-variant bg-surface rounded px-1 border border-outline-variant">⌘K</kbd>
            </div>
        </div>

        {{-- Right: actions + profile --}}
        <div class="flex items-center gap-sm">
            <button class="w-8 h-8 flex items-center justify-center rounded text-on-surface-variant hover:bg-surface-container-high transition-colors">
                <span class="material-symbols-outlined" style="font-size:20px">notifications</span>
            </button>
            <button class="w-8 h-8 flex items-center justify-center rounded text-on-surface-variant hover:bg-surface-container-high transition-colors">
                <span class="material-symbols-outlined" style="font-size:20px">help_outline</span>
            </button>
            <div class="w-px h-5 bg-outline-variant mx-xs"></div>
            <div class="flex items-center gap-sm cursor-pointer">
                <div class="w-8 h-8 rounded-full bg-secondary-container border border-outline-variant flex items-center justify-center text-on-secondary-container font-semibold text-[13px] select-none">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                </div>
                <span class="text-[13px] text-on-surface-variant hidden lg:block max-w-[120px] truncate">
                    {{ auth()->user()->name ?? '' }}
                </span>
            </div>
        </div>
    </header>

    {{-- ===== PAGE CANVAS ===== --}}
    <main class="flex-1 mt-16 p-lg overflow-y-auto">
        @if (session('status'))
            <div class="mb-md px-md py-sm rounded-lg border text-[13px]"
                 style="background:rgb(from #6ad191 r g b / 0.08);border-color:rgb(from #6ad191 r g b / 0.25);color:#6ad191">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot ?? '' }}
        @yield('content')
    </main>
</div>

{{-- ===== TOAST SYSTEM ===== --}}
<div id="toast-container" class="fixed bottom-lg right-lg z-[9999] flex flex-col gap-sm pointer-events-none"></div>

@livewireScripts
@stack('scripts')
<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('toast', ({ type, msg }) => {
            const container = document.getElementById('toast-container');
            const colors = {
                success: { bg: 'rgb(from #6ad191 r g b / 0.1)', border: 'rgb(from #6ad191 r g b / 0.3)', text: '#6ad191' },
                error:   { bg: 'rgb(from #ffb4ab r g b / 0.1)', border: 'rgb(from #ffb4ab r g b / 0.3)', text: '#ffb4ab' },
                warning: { bg: 'rgb(from #ffb786 r g b / 0.1)', border: 'rgb(from #ffb786 r g b / 0.3)', text: '#ffb786' },
            };
            const c = colors[type] ?? colors.success;
            const el = document.createElement('div');
            el.style.cssText = `
                background:${c.bg};border:1px solid ${c.border};color:${c.text};
                border-radius:6px;padding:10px 16px;font-size:13px;max-width:320px;
                box-shadow:0 4px 16px rgba(0,0,0,.5);pointer-events:all;
                animation:toast-in .2s ease;
            `;
            el.textContent = msg;
            container.appendChild(el);
            setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(() => el.remove(), 300); }, 4000);
        });
    });
</script>
<style>
    @keyframes toast-in { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
</style>
</body>
</html>
