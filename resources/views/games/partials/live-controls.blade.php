{{--
    Modo en directo: la ronda que aún no habías visto aparece evento a evento, con un cañonazo por cada muerte.
    Sin JavaScript se ve todo de golpe, como siempre. Las preferencias se guardan en el navegador (localStorage).
--}}
<style>
    /* Mientras se cuenta la ronda: lo que aún no ha pasado no se ve */
    html.live-pending [data-live-round] [data-live-item]:not(.live-shown),
    html.live-pending [data-live-after] {
        display: none;
    }

    /* El estado de los tributos diría quién muere antes de tiempo */
    [data-live-aside] {
        transition: opacity 0.6s, filter 0.6s;
    }
    html.live-pending [data-live-aside] {
        opacity: 0.15;
        filter: blur(6px);
        pointer-events: none;
        user-select: none;
    }

    .live-shown {
        animation: live-in 0.5s ease-out;
    }
    .live-shown.live-death {
        animation: live-in 0.5s ease-out, live-flash 1.4s ease-out;
    }
    .live-sky [data-live-face] {
        animation: live-rise 1s ease-out both;
        animation-delay: calc(var(--live-index) * 0.6s + 0.4s);
    }

    @keyframes live-in {
        from { opacity: 0; transform: translateY(6px); }
    }
    @keyframes live-flash {
        from { background-color: color-mix(in srgb, var(--color-blood) 35%, transparent); }
    }
    @keyframes live-rise {
        from { opacity: 0; transform: translateY(14px) scale(0.9); filter: blur(4px); }
    }

    @media (prefers-reduced-motion: reduce) {
        .live-shown, .live-shown.live-death, .live-sky [data-live-face] { animation: none; }
    }
</style>

@if ($live)
    <script>
        // Antes de pintar la ronda, para que no se vea un instante entera y luego desaparezca
        try {
            const saved = localStorage.getItem('juegos.live');
            const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (saved === 'on' || (saved === null && ! reduced)) {
                document.documentElement.classList.add('live-pending');
            }
        } catch (e) {}
    </script>
@endif

<div class="mb-6 flex flex-wrap items-center justify-end gap-2 text-sm" data-live-controls hidden>
    <button type="button" class="btn-quiet" data-live-skip hidden>Saltar al final de la ronda</button>
    <button type="button" class="btn-quiet" data-live-toggle aria-pressed="true">En directo</button>
    <button type="button" class="btn-quiet" data-live-sound aria-pressed="true">Cañonazos</button>
</div>
