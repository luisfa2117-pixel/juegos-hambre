<?php

namespace App\Http\Controllers;

use App\Http\Requests\CharacterRequest;
use App\Models\Character;
use App\Models\District;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CharacterController extends Controller
{
    /** Lista de personajes, agrupados por distrito */
    public function index(Request $request): View
    {
        // whereBelongsTo: solo los personajes de quien ha iniciado sesión
        $districts = District::orderBy('name')
            ->with(['characters' => fn ($query) => $query->whereBelongsTo($request->user())->orderBy('name')->withStats()])
            ->get();

        return view('characters.index', [
            'districts' => $districts,
            'total' => $districts->sum(fn (District $district) => $district->characters->count()),
        ]);
    }

    public function create(): View
    {
        return view('characters.create', [
            'character' => new Character(),
            'districts' => District::orderBy('name')->get(),
        ]);
    }

    public function store(CharacterRequest $request): RedirectResponse
    {
        // make() crea el personaje ya asignado al usuario (user_id), sin guardarlo todavía
        $character = $request->user()->characters()->make($request->safe()->only(['name', 'gender', 'district_id', 'personality']));

        if ($request->hasFile('image')) {
            // Guarda el fichero en storage/app/public/characters con un nombre único
            $character->image_path = $request->file('image')->store('characters', 'public');
        }

        $character->save();

        return redirect()->route('characters.index')
            ->with('status', "{$character->name} ya está en la lista.");
    }

    /** La ficha del personaje: estadísticas y sus partidas */
    public function show(Character $character): View
    {
        // Si no es suyo, Laravel corta aquí con un error 403 (ver CharacterPolicy)
        Gate::authorize('view', $character);

        return view('characters.show', [
            // Volvemos a pedirlo con withStats() para tener sus estadísticas
            'character' => Character::withStats()->with('district')->findOrFail($character->id),
            'appearances' => $character->gameTributes()->with('game')->orderByDesc('game_id')->limit(30)->get(),
        ]);
    }

    // Laravel busca el personaje por el id de la URL (route model binding)
    public function edit(Character $character): View
    {
        Gate::authorize('update', $character);

        return view('characters.edit', [
            'character' => $character,
            'districts' => District::orderBy('name')->get(),
        ]);
    }

    public function update(CharacterRequest $request, Character $character): RedirectResponse
    {
        Gate::authorize('update', $character);

        $oldImage = $character->image_path;
        $character->fill($request->safe()->only(['name', 'gender', 'district_id', 'personality']));

        if ($request->hasFile('image')) {
            $character->image_path = $request->file('image')->store('characters', 'public');
        } elseif ($request->boolean('remove_image')) {
            $character->image_path = null;
        }

        $character->save();

        // La foto vieja se borra DESPUÉS de guardar: si algo fallara antes, no la perderíamos
        if ($oldImage !== null && $oldImage !== $character->image_path) {
            Storage::disk('public')->delete($oldImage);
        }

        return redirect()->route('characters.index')
            ->with('status', "Cambios guardados en {$character->name}.");
    }

    public function destroy(Character $character): RedirectResponse
    {
        Gate::authorize('delete', $character);

        $character->delete();

        return redirect()->route('characters.index')
            ->with('status', "{$character->name} ya no está en la lista. Sus partidas guardadas se conservan.");
    }
}
