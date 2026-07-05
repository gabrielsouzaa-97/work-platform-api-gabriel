@extends('layouts.app')

@section('page-title', 'Documentação API')

@push('head')
    @vite('resources/js/docs-api.js')
@endpush

@section('content')
<div class="mb-md flex flex-wrap items-center gap-sm rounded-lg border border-outline-variant bg-surface-container px-md py-sm text-[12px] text-on-surface-variant">
    <span class="inline-flex items-center gap-xs font-semibold uppercase tracking-wide">
        <span class="material-symbols-outlined" style="font-size:16px">deployed_code</span>
        Ambiente: {{ config('app.env') }}
    </span>
    <span class="text-outline-variant" aria-hidden="true">|</span>
    <span>Spec v{{ $specVersion }}</span>
</div>
<div
    id="api-docs"
    class="min-h-[calc(100vh-6rem)] -mx-margin"
    data-spec-url="{{ $specUrl }}"
    data-api-base-url="{{ $apiBaseUrl }}"
></div>
@endsection
