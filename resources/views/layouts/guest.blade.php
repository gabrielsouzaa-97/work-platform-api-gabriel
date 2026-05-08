<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'meWork360') }}</title>
    @livewireStyles
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        body {
            min-height: 100vh;
            background: #0f1117;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .guest-card {
            width: 100%;
            max-width: 400px;
            background: #1a1d27;
            border: 1px solid #2d3748;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 24px rgba(0,0,0,0.4);
        }
        .brand {
            text-align: center;
            margin-bottom: 2rem;
        }
        .brand-name {
            font-size: 1.4rem;
            font-weight: 700;
            color: #63b3ed;
            letter-spacing: -0.5px;
        }
        .brand-sub {
            font-size: 0.78rem;
            color: #718096;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <div class="guest-card">
        <div class="brand">
            <div class="brand-name">meWork360</div>
            <div class="brand-sub">Painel de Operacoes</div>
        </div>

        @if (session('status'))
            <div style="background:#2d3748;border:1px solid #4a5568;border-radius:6px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.875rem;color:#a0aec0;">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot }}
    </div>
    @livewireScripts
</body>
</html>
