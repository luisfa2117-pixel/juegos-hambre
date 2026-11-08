@props(['game'])

@use('App\Enums\Terrain')

{{-- La arena (si no es la de siempre) y las reglas especiales de una partida, como etiquetas. Si no hay nada, no se muestra nada --}}
@php $terrain = $game->terrain ?? Terrain::City; @endphp
@if ($terrain !== Terrain::City || $game->quell || ! $game->gameRules()->isEmpty())
    <ul {{ $attributes->class('flex flex-wrap items-center gap-2') }} aria-label="Arena y reglas de la partida">
        @if ($terrain !== Terrain::City)
            <li class="rounded-sm bg-night/20 px-2 py-0.5 font-condensed text-sm font-semibold text-night" title="{{ $terrain->description() }}">
                {{ $terrain->label() }}
            </li>
        @endif

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
