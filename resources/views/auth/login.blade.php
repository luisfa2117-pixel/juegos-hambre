<x-layout title="Entrar">
    <div class="mx-auto max-w-sm py-8">
        <h1 class="font-display text-4xl">Entrar</h1>
        <p class="mt-1 text-ash-muted">Tus personajes y partidas te esperan.</p>

        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
            @csrf

            <div>
                <label for="email" class="field-label">Correo</label>
                <input id="email" name="email" type="email" required autofocus autocomplete="email"
                       value="{{ old('email') }}" class="field">
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="field-label">Contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="field">
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" name="remember" value="1" class="accent-hazard">
                Recordarme en este dispositivo
            </label>

            <button type="submit" class="btn w-full">Entrar</button>
        </form>

        <div class="mt-8 border-t border-asphalt-line pt-6 text-center">
            <p class="text-sm text-ash-muted">¿Solo quieres probarlo?</p>
            <form method="POST" action="{{ route('guest.store') }}" class="mt-2">
                @csrf
                <button type="submit" class="btn-quiet">Probar sin registrarse</button>
            </form>

            @if (Route::has('register'))
                <p class="mt-4 text-sm text-ash-muted">
                    ¿Quieres guardar tus partidas? <a href="{{ route('register') }}" class="text-hazard underline">Crea una cuenta</a>
                </p>
            @endif
        </div>
    </div>
</x-layout>
