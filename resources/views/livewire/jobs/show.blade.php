<div
        class="max-w-[1400px] mx-auto space-y-gutter"
        @if ($job->state === 'running')
            wire:poll.5s
        @endif
>
    <nav class="text-[12px] text-on-surface-variant flex flex-wrap items-center gap-xs">
        <a href="{{ route('queue.index') }}" class="hover:text-primary transition-colors font-medium">Fila de Provisionamento</a>
        <span class="text-outline" aria-hidden="true">→</span>
        <span class="text-on-surface">Job {{ Str::limit($job->job_id, 8, '') }}…</span>
    </nav>

    <div class="flex flex-col gap-xs">
        <div class="flex flex-wrap items-center gap-sm">
            <h2 class="font-bold text-[28px] leading-tight text-on-surface">
                Job #{{ Str::limit($job->job_id, 8, '') }}…
            </h2>
            <span class="inline-flex items-center gap-xs px-sm py-[3px] rounded-full text-[11px] font-semibold uppercase tracking-wide state-{{ $job->state }}">
                @if ($job->state === 'running')
                    <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse block"></span>
                @endif
                {{ $job->state }}
            </span>
        </div>
        <p class="text-[13px] text-on-surface-variant">
            <code class="font-mono">{{ $job->customer_slug }}</code>
            <span class="mx-xs text-outline">·</span>
            <code class="font-mono text-secondary">{{ $job->job_type }}</code>
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-md">
        <div class="bg-surface-container-low border border-outline-variant rounded-xl p-md">
            <div class="text-[11px] uppercase tracking-wide text-on-surface-variant mb-xs">Job ID</div>
            <div class="font-mono text-[12px] text-on-surface break-all">{{ $job->job_id }}</div>
        </div>
        <div class="bg-surface-container-low border border-outline-variant rounded-xl p-md">
            <div class="text-[11px] uppercase tracking-wide text-on-surface-variant mb-xs">Customer</div>
            <code class="font-mono text-[13px] text-on-surface">{{ $job->customer_slug }}</code>
        </div>
        <div class="bg-surface-container-low border border-outline-variant rounded-xl p-md">
            <div class="text-[11px] uppercase tracking-wide text-on-surface-variant mb-xs">Estado</div>
            <span class="inline-flex items-center gap-xs px-sm py-[3px] rounded-full text-[11px] font-semibold uppercase tracking-wide state-{{ $job->state }}">
                @if ($job->state === 'running')
                    <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse block"></span>
                @endif
                {{ $job->state }}
            </span>
        </div>
        <div class="bg-surface-container-low border border-outline-variant rounded-xl p-md">
            <div class="text-[11px] uppercase tracking-wide text-on-surface-variant mb-xs">Exit Code</div>
            @if ($job->exit_code !== null)
                <code class="font-mono text-[12px] {{ $job->exit_code === 0 ? 'text-[#6ad191]' : 'text-error' }}">{{ $job->exit_code }}</code>
            @else
                <span class="font-mono text-[12px] text-on-surface-variant">—</span>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-md">
        <div class="bg-surface-container-low border border-outline-variant rounded-xl p-md">
            <div class="text-[11px] uppercase tracking-wide text-on-surface-variant mb-xs">Enfileirado</div>
            <div class="font-mono text-[12px] text-on-surface">{{ $job->queued_at?->format('d/m/Y H:i:s') ?? '—' }}</div>
        </div>
        <div class="bg-surface-container-low border border-outline-variant rounded-xl p-md">
            <div class="text-[11px] uppercase tracking-wide text-on-surface-variant mb-xs">Iniciado</div>
            <div class="font-mono text-[12px] text-on-surface">{{ $job->started_at?->format('d/m/Y H:i:s') ?? '—' }}</div>
        </div>
        <div class="bg-surface-container-low border border-outline-variant rounded-xl p-md">
            <div class="text-[11px] uppercase tracking-wide text-on-surface-variant mb-xs">Concluído</div>
            <div class="font-mono text-[12px] text-on-surface">{{ $job->finished_at?->format('d/m/Y H:i:s') ?? '—' }}</div>
        </div>
        <div class="bg-surface-container-low border border-outline-variant rounded-xl p-md">
            <div class="text-[11px] uppercase tracking-wide text-on-surface-variant mb-xs">Duração</div>
            <div class="font-mono text-[12px] text-on-surface">{{ $durationLabel ?? '—' }}</div>
        </div>
    </div>

    <div class="relative rounded-xl border border-outline-variant/60 bg-black overflow-hidden">
        <div class="relative p-md" x-data="{ scrollToBottom() { const t = this.$refs.terminal; if (t) t.scrollTop = t.scrollHeight; } }">
            @if ($job->state === 'running')
                <div class="mb-sm text-[11px] text-primary font-mono flex items-center gap-xs">
                    <span aria-hidden="true">⟳</span>
                    <span>Atualizando a cada 5s</span>
                </div>
            @endif
            <div x-ref="terminal" class="font-mono text-[12px] max-h-[500px] overflow-y-auto pr-sm pb-[44px] space-y-0.5">
                @forelse ($logLines as $line)
                    @php
                        $colorMap = [
                            '[INFO]' => 'text-primary',
                            '[TASK]' => 'text-secondary',
                            '[WARN]' => 'text-tertiary',
                            '[EXEC]' => 'text-on-surface-variant',
                            '[ERROR]' => 'text-error',
                        ];
                        $lineColor = 'text-on-surface-variant';
                        foreach ($colorMap as $prefix => $color) {
                            if (str_starts_with($line, $prefix)) {
                                $lineColor = $color;
                                break;
                            }
                        }
                    @endphp
                    <div class="{{ $lineColor }} leading-relaxed whitespace-pre-wrap wrap-break-word">{{ $line }}</div>
                @empty
                    <p class="text-outline text-[12px]">Nenhum log disponível.</p>
                @endforelse
            </div>
            <button
                    type="button"
                    @click="scrollToBottom()"
                    class="absolute bottom-md right-md z-10 inline-flex items-center gap-xs px-sm py-xs rounded-lg border border-outline-variant/60 bg-surface-container-low text-[11px] font-semibold uppercase tracking-wide text-on-surface hover:border-primary/50 transition-colors shadow-lg"
            >
                <span aria-hidden="true">↓</span>
                Scroll to Bottom
            </button>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-md">
        <a href="{{ route('queue.index') }}"
           class="inline-flex items-center gap-xs px-md py-sm border border-outline-variant rounded-lg text-[12px] font-semibold text-on-surface hover:bg-surface-container transition-colors">
            <span aria-hidden="true">←</span>
            Voltar à Fila
        </a>
        @if (count($logLines) > 0)
            <button type="button" wire:click="exportLog"
                    class="inline-flex items-center gap-xs px-md py-sm border border-outline-variant rounded-lg text-[12px] font-semibold text-on-surface hover:bg-surface-container transition-colors">
                <span class="material-symbols-outlined" style="font-size:18px">download</span>
                Export Log
            </button>
        @endif
    </div>
</div>
