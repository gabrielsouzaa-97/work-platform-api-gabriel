<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'meWork360') }} — Painel</title>
    @livewireStyles
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { font-family: 'Inter', system-ui, sans-serif; }
        body { min-height: 100vh; background: #0f1117; color: #e2e8f0; }
        .topbar {
            background: #1a1d27;
            border-bottom: 1px solid #2d3748;
            padding: .75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .brand { font-size: 1rem; font-weight: 700; color: #63b3ed; }
        .nav { display: flex; gap: 1.25rem; align-items: center; }
        .nav a { color: #a0aec0; text-decoration: none; font-size: .875rem; }
        .nav a:hover { color: #e2e8f0; }
        .logout-btn {
            background: none;
            border: 1px solid #4a5568;
            color: #a0aec0;
            border-radius: 4px;
            padding: .25rem .75rem;
            font-size: .8125rem;
            cursor: pointer;
        }
        .logout-btn:hover { border-color: #718096; color: #e2e8f0; }
        .main { padding: 1.5rem; }
        .alert-success {
            background: #1c3a2f;
            border: 1px solid #2f6a4e;
            border-radius: 6px;
            padding: .75rem 1rem;
            margin-bottom: 1rem;
            font-size: .875rem;
            color: #68d391;
        }
    </style>
</head>
<body>
    <nav class="topbar">
        <span class="brand">meWork360</span>
        <div class="nav">
            <a href="{{ route('customers.index') }}">Customers</a>
            <a href="{{ route('queue.index') }}">Fila</a>
            @can('manage-cluster-servers')
                <a href="{{ route('cluster-servers.index') }}">Clusters</a>
            @endcan
            @can('manage-operators')
                <a href="{{ route('operators.index') }}">Operadores</a>
                <a href="{{ route('audit.index') }}">Audit</a>
            @endcan
            <form action="{{ route('logout') }}" method="POST" style="margin:0">
                @csrf
                <button type="submit" class="logout-btn">Sair</button>
            </form>
        </div>
    </nav>
    <main class="main">
        @if (session('status'))
            <div class="alert-success">{{ session('status') }}</div>
        @endif
        {{ $slot ?? '' }}
        @yield('content')
    </main>
    @livewireScripts
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('toast', ({ type, msg }) => {
                const el = document.createElement('div');
                const colors = { success: '#1c3a2f', error: '#3a2020', warning: '#2d3a1a' };
                const text   = { success: '#68d391', error: '#fc8181', warning: '#c6f135' };
                el.style.cssText = `position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
                    background:${colors[type]||colors.success};color:${text[type]||text.success};
                    border-radius:6px;padding:.75rem 1rem;font-size:.875rem;max-width:320px;
                    box-shadow:0 4px 12px rgba(0,0,0,.4);`;
                el.textContent = msg;
                document.body.appendChild(el);
                setTimeout(() => el.remove(), 4000);
            });
        });
    </script>
</body>
</html>
