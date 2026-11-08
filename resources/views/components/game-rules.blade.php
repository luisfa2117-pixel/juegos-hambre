@props(['game'])

{{-- Las reglas especiales de una partida, como etiquetas. Si no tiene ninguna, no se muestra nada --}}
@if ($game->quell || ! $game->gameRules()->isEmpty())
    <ul {{ $attributes->class('flex flex-wrap items-center gap-2') }} aria-label="Reglas de la partida">
        @if ($game->quell)
            <li class="rounded-sm bg-blood px-2 py-0.5 font-condensed text-sm font-semibold uppercase tracking-wide text-ash">
                Vasallaje de los 25
            </li>
        @endif

        @foreach ($game->gameRules()->all() as $rule)
            <li class="rounded-sm border border-hazard/60 px-2 py-0.5 font-condensed text-sm text-hazard" title="{{ $rule->description() }}">
                {{ $rule->label() }}
            </li>
        @endforeach
    </ul>
@endif
