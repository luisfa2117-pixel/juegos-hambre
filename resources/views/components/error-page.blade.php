{{-- Las páginas de error (404, 403...). Uso: <x-error-page code="404" title="..." message="..." /> --}}
@props(['code', 'title', 'message'])

<x-layout :title="$title">
    <div class="py-16">
        <p class="font-display text-7xl text-hazard">{{ $code }}</p>
        <h1 class="mt-4 font-display text-4xl">{{ $title }}</h1>
        <p class="mt-3 max-w-xl text-ash-muted">{{ $message }}</p>
        <a href="{{ route('home') }}" class="btn mt-8">Volver al inicio</a>
    </div>
</x-layout>
