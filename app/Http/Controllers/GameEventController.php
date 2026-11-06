<?php

namespace App\Http\Controllers;

use App\Enums\EventCategory;
use App\Http\Requests\GameEventRequest;
use App\Models\GameEvent;
use App\Models\Item;
use App\Support\EventPreview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameEventController extends Controller
{
    public function index(Request $request): View
    {
        $category = EventCategory::tryFrom((string) $request->query('category'));

        return view('events.index', [
            'events' => GameEvent::with(['requiresItem', 'givesItem'])
                ->when($category, fn ($query) => $query->where('category', $category))
                ->orderBy('category')
                ->orderBy('text')
                ->get(),
            'category' => $category,
            'counts' => GameEvent::selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category'),
        ]);
    }

    public function create(Request $request): View
    {
        $event = new GameEvent([
            'category' => EventCategory::tryFrom((string) $request->query('category')) ?? EventCategory::Solo,
            'actor_hp' => 0, 'actor_energy' => 0, 'target_hp' => 0, 'target_energy' => 0,
        ]);

        return view('events.create', ['event' => $event, 'items' => Item::orderBy('name')->get()]);
    }

    public function store(GameEventRequest $request): RedirectResponse
    {
        $event = GameEvent::create($request->eventData());

        return redirect()->route('events.index', ['category' => $event->category])
            ->with('status', 'Evento creado. Ya puede salir en las próximas partidas.');
    }

    public function edit(GameEvent $event): View
    {
        return view('events.edit', ['event' => $event, 'items' => Item::orderBy('name')->get()]);
    }

    public function update(GameEventRequest $request, GameEvent $event): RedirectResponse
    {
        $event->update($request->eventData());

        return redirect()->route('events.index', ['category' => $event->category])
            ->with('status', 'Cambios guardados.');
    }

    public function destroy(GameEvent $event): RedirectResponse
    {
        // El motor necesita al menos un evento de una persona (individual o descubrimiento)
        $soloCategories = [EventCategory::Solo, EventCategory::Discovery];
        $isLastSolo = in_array($event->category, $soloCategories, true)
            && GameEvent::whereIn('category', $soloCategories)->count() === 1;

        if ($isLastSolo) {
            return back()->with('error', 'No se puede eliminar: es el último evento de una persona, y las partidas lo necesitan.');
        }

        $event->delete();

        return redirect()->route('events.index', ['category' => $event->category])
            ->with('status', 'Evento eliminado.');
    }

    /** La vista previa en directo del formulario: recibe un texto y devuelve JSON */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate(['text' => ['nullable', 'string', 'max:255']]);

        return response()->json(EventPreview::both($validated['text'] ?? ''));
    }
}
