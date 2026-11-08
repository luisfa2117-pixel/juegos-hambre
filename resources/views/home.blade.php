<x-layout title="Simulador">
    <section class="py-10 sm:py-16">
        <div class="hazard-stripes mb-8 h-3 w-40 rounded-sm" aria-hidden="true"></div>

        <h1 class="max-w-3xl font-display text-5xl leading-tight sm:text-6xl">Juegos del Hambre</h1>
        <p class="mt-4 max-w-2xl text-xl text-ash-muted">
            Un simulador ambientado en una Norteamérica futurista en ruinas. Elige a los tributos,
            mira cómo sobreviven ronda a ronda y, si quieres, échales una mano desde la grada.
        </p>

        <div class="mt-8 flex flex-wrap items-center gap-4">
            <form method="POST" action="{{ route('guest.store') }}">
                @csrf
                <button type="submit" class="btn px-6 py-3 text-lg">Probar sin registrarse</button>
            </form>
            <a href="{{ route('login') }}" class="text-ash-muted underline hover:text-hazard">Ya tengo cuenta</a>
        </div>
        <p class="mt-3 max-w-xl text-sm text-ash-muted">
            Te creamos una cuenta de invitado con 12 tributos, sin pedirte ningún dato. Se guarda en este navegador
            y se borra si no la usas en {{ config('juegos.guest_days') }} días.
        </p>
    </section>

    <section class="grid gap-x-10 gap-y-8 border-t border-asphalt-line py-10 sm:grid-cols-3">
        <div>
            <h2 class="font-condensed text-2xl font-semibold">Pasa de todo</h2>
            <p class="mt-2 text-ash-muted">
                Casi 500 eventos: el baño de sangre en la Cornucopia, días y noches, alianzas que se rompen
                con una traición y catástrofes de los Vigilantes que afectan a todos a la vez.
            </p>
        </div>
        <div>
            <h2 class="font-condensed text-2xl font-semibold">Y tiene consecuencias</h2>
            <p class="mt-2 text-ash-muted">
                El botiquín que encuentra un tributo lo lleva en la mochila y lo usa cuando está herido.
                Cada uno tiene su distrito y su personalidad: el traicionero traiciona y el cobarde huye.
            </p>
        </div>
        <div>
            <h2 class="font-condensed text-2xl font-semibold">Tú también juegas</h2>
            <p class="mt-2 text-ash-muted">
                Crea tus propios tributos, envía hasta tres regalos de patrocinador por partida
                y sigue quién gana más y quién siempre cae en la Cornucopia.
            </p>
        </div>
    </section>
</x-layout>
