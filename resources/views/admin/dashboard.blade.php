@extends('layouts.app')

@section('page-title', 'Dashboard')

@push('head')
    @vite('resources/js/pages/dashboard.js')
@endpush

@section('content')
<div class="max-w-[1400px] mx-auto space-y-gutter">

    {{-- ===== Row 1: Hero + Queue ===== --}}
    <div class="grid grid-cols-12 gap-gutter">

        {{-- Status Hero --}}
        <div class="col-span-12 lg:col-span-8 bg-surface border border-outline-variant rounded-lg p-lg relative overflow-hidden flex flex-col justify-between min-h-[200px]">
            <div class="absolute right-0 top-0 w-1/2 h-full pointer-events-none opacity-20"
                 style="background:radial-gradient(circle at 100% 0%, #4d8eff, transparent 70%)"></div>
            <div class="relative z-10">
                <div class="flex items-center gap-sm mb-sm">
                    <span class="w-2 h-2 rounded-full bg-primary animate-pulse block"></span>
                    <span class="font-semibold text-[11px] tracking-widest uppercase text-primary">Sistema Operacional</span>
                </div>
                <h2 class="font-bold text-[28px] leading-tight text-on-surface mb-sm">API meWork360 Deployer</h2>
                <p class="text-[13px] text-on-surface-variant max-w-2xl leading-relaxed">
                    Gerenciamento centralizado de credenciais e orquestração de provisionamento Nextcloud via SSH/webhook.
                    Somente operações via API.
                </p>
            </div>
            <div class="relative z-10 flex gap-sm mt-lg">
                <a href="{{ route('audit.index') }}"
                   class="bg-surface-container border border-outline-variant hover:border-primary text-on-surface text-[13px] py-sm px-md rounded transition-colors">
                    Ver Logs de Requisição
                </a>
                <a href="{{ route('queue.index') }}"
                   class="bg-transparent border border-outline-variant hover:bg-surface-container-high text-on-surface text-[13px] py-sm px-md rounded transition-colors">
                    Ver Provisionamentos
                </a>
            </div>
        </div>

        {{-- Provisioning Queue Summary --}}
        <div class="col-span-12 lg:col-span-4 bg-surface border border-outline-variant rounded-lg p-lg flex flex-col">
            <h3 class="font-semibold text-[11px] tracking-widest uppercase text-on-surface-variant mb-md flex items-center gap-sm">
                <span class="material-symbols-outlined" style="font-size:16px">dynamic_feed</span>
                Fila de Provisionamento
            </h3>
            <div class="flex-1 flex flex-col justify-center">
                <div class="flex items-end justify-between mb-sm">
                    <span class="font-bold text-[32px] leading-none text-on-surface">{{ $queueStats['running'] + $queueStats['queued'] }}</span>
                    <span class="text-[12px] text-on-surface-variant mb-1">Jobs Pendentes</span>
                </div>
                <div class="w-full bg-surface-container-highest h-1.5 rounded-full overflow-hidden mb-sm">
                    <div class="bg-primary h-full rounded-full transition-all"
                         style="width:{{ $queueStats['running'] + $queueStats['queued'] > 0 ? min(100, (($queueStats['running']) / max(1, $queueStats['running'] + $queueStats['queued'])) * 100) : 0 }}%"></div>
                </div>
                <div class="flex justify-between text-[11px] tracking-wide uppercase text-on-surface-variant">
                    <span>Running: {{ $queueStats['running'] }}</span>
                    <span class="{{ $queueStats['running'] + $queueStats['queued'] === 0 ? 'text-primary' : 'text-tertiary' }}">
                        {{ $queueStats['running'] + $queueStats['queued'] === 0 ? 'Saudável' : 'Processando' }}
                    </span>
                </div>
            </div>
            <div class="mt-md pt-md border-t border-surface-container-highest grid grid-cols-2 gap-sm">
                <div>
                    <span class="block text-[11px] uppercase tracking-wide text-on-surface-variant mb-xs">Sucesso (24h)</span>
                    <span class="text-[15px] font-semibold text-on-surface">{{ $queueStats['success_24h'] }}</span>
                </div>
                <div>
                    <span class="block text-[11px] uppercase tracking-wide text-on-surface-variant mb-xs">Falha (24h)</span>
                    <span class="text-[15px] font-semibold {{ $queueStats['failed_24h'] > 0 ? 'text-error' : 'text-on-surface' }}">
                        {{ $queueStats['failed_24h'] }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Row 2: KPIs ===== --}}
    <div class="grid grid-cols-12 gap-gutter">

        {{-- KPI: Active API Keys --}}
        <div class="col-span-12 md:col-span-4 bg-surface border border-outline-variant rounded-lg p-md flex flex-col justify-between h-[140px]">
            <div class="flex justify-between items-start">
                <span class="text-[11px] uppercase tracking-wide text-on-surface-variant">Credenciais Ativas</span>
                <span class="material-symbols-outlined text-primary" style="font-size:20px">vpn_key</span>
            </div>
            <div>
                <div class="flex items-end gap-sm">
                    <span class="font-bold text-[30px] leading-none text-on-surface">{{ $kpis['active_keys'] }}</span>
                    @if($kpis['revoked_keys'] > 0)
                        <span class="text-[11px] uppercase tracking-wide text-error bg-error/10 px-xs py-0.5 rounded mb-1">
                            {{ $kpis['revoked_keys'] }} revogada{{ $kpis['revoked_keys'] > 1 ? 's' : '' }}
                        </span>
                    @endif
                </div>
                <p class="mt-sm text-[12px] text-on-surface-variant">API keys para consumo externo</p>
            </div>
        </div>

        {{-- KPI: Audit Events Today --}}
        <div class="col-span-12 md:col-span-4 bg-surface border border-outline-variant rounded-lg p-md flex flex-col justify-between h-[140px]">
            <div class="flex justify-between items-start">
                <span class="text-[11px] uppercase tracking-wide text-on-surface-variant">Eventos Hoje</span>
                <span class="material-symbols-outlined text-tertiary" style="font-size:20px">swap_vert</span>
            </div>
            <div>
                <div class="flex items-end gap-sm">
                    <span class="font-bold text-[30px] leading-none text-on-surface">{{ $kpis['audit_today'] }}</span>
                    <span class="text-[11px] uppercase tracking-wide text-tertiary/80 bg-tertiary/10 px-xs py-0.5 rounded mb-1">Audit</span>
                </div>
                <p class="mt-sm text-[12px] text-on-surface-variant">Requisições registradas nas últimas 24h</p>
            </div>
        </div>

        {{-- KPI: Cluster Health --}}
        <div class="col-span-12 md:col-span-4 bg-surface border border-outline-variant rounded-lg p-md flex flex-col justify-between h-[140px]">
            <div class="flex justify-between items-start">
                <span class="text-[11px] uppercase tracking-wide text-on-surface-variant">Cluster Status</span>
                <span class="material-symbols-outlined {{ $kpis['clusters_active'] > 0 && $kpis['clusters_total'] === $kpis['clusters_active'] ? 'text-[#6ad191]' : 'text-error' }}" style="font-size:20px">
                    {{ $kpis['clusters_active'] > 0 && $kpis['clusters_total'] === $kpis['clusters_active'] ? 'check_circle' : 'warning' }}
                </span>
            </div>
            <div>
                <div class="flex items-end gap-sm">
                    <span class="font-bold text-[30px] leading-none text-on-surface">{{ $kpis['clusters_active'] }}/{{ $kpis['clusters_total'] }}</span>
                </div>
                <p class="mt-sm text-[12px] text-on-surface-variant">Servidores upstream ativos</p>
            </div>
        </div>
    </div>

    {{-- ===== Row 3: Jobs Chart ===== --}}
    <div class="bg-surface border border-outline-variant rounded-lg overflow-hidden">
        <div class="px-md py-[14px] border-b border-outline-variant flex justify-between items-center bg-surface-container-lowest">
            <h3 class="font-semibold text-[16px] text-on-surface">Jobs — Últimos 7 dias</h3>
            <a href="{{ route('queue.index') }}"
               class="text-[11px] uppercase tracking-wide text-primary hover:text-primary-fixed transition-colors flex items-center gap-xs">
                Ver fila
                <span class="material-symbols-outlined" style="font-size:14px">arrow_forward</span>
            </a>
        </div>
        <div class="p-md" style="height:220px">
            <canvas id="jobs-chart"
                    data-labels="{{ json_encode($chartLabels) }}"
                    data-success="{{ json_encode($chartSuccess) }}"
                    data-failed="{{ json_encode($chartFailed) }}"
                    style="width:100%;height:100%">
            </canvas>
        </div>
    </div>

    {{-- ===== Row 4: Recent Activity ===== --}}
    <div class="bg-surface border border-outline-variant rounded-lg overflow-hidden">
        <div class="px-md py-[14px] border-b border-outline-variant flex justify-between items-center bg-surface-container-lowest">
            <h3 class="font-semibold text-[16px] text-on-surface">Atividade Recente</h3>
            <a href="{{ route('audit.index') }}"
               class="text-[11px] uppercase tracking-wide text-primary hover:text-primary-fixed transition-colors flex items-center gap-xs">
                Ver tudo
                <span class="material-symbols-outlined" style="font-size:14px">arrow_forward</span>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface border-b border-outline-variant">
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-md py-[10px] w-10">Status</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-md py-[10px]">Ação</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-md py-[10px]">Recurso</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-md py-[10px]">Operador</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-md py-[10px] text-right">Horário</th>
                    </tr>
                </thead>
                <tbody class="text-[13px] text-on-surface">
                    @forelse ($recentActivity as $log)
                        <tr class="border-b border-outline-variant hover:bg-surface-container-high transition-colors">
                            <td class="px-md py-[12px]">
                                <div class="w-2 h-2 rounded-full mx-auto
                                    {{ str_contains($log->action, 'fail') || str_contains($log->action, 'error') || str_contains($log->action, 'delete')
                                        ? 'bg-error' : 'bg-primary' }}">
                                </div>
                            </td>
                            <td class="px-md py-[12px]">
                                <code class="font-mono text-[12px] bg-surface-container-highest px-xs py-0.5 rounded text-primary">
                                    {{ $log->action }}
                                </code>
                            </td>
                            <td class="px-md py-[12px] text-on-surface-variant">
                                <span class="text-[11px] uppercase tracking-wide">{{ $log->resource_type }}</span>
                                <span class="block font-mono text-[11px] text-on-surface-variant/70 truncate max-w-[140px]">
                                    {{ Str::limit($log->resource_id, 12) }}
                                </span>
                            </td>
                            <td class="px-md py-[12px] text-on-surface-variant">
                                {{ $log->actor?->name ?? '—' }}
                            </td>
                            <td class="px-md py-[12px] text-right text-on-surface-variant text-[12px]">
                                {{ $log->created_at?->diffForHumans() ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-md py-xl text-center text-on-surface-variant text-[13px]">
                                Nenhuma atividade registrada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
