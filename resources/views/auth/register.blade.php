<x-layout title="Crear cuenta">
    <div class="mx-auto max-w-sm py-8">
        <h1 class="font-display text-4xl">Crear cuenta</h1>
        <p class="mt-1 text-ash-muted">Empezarás con 8 personajes listos para entrar en la arena.</p>

        <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-5">
            @csrf

            <div>
                <label for="name" class="field-label">Nombre</label>
                <input id="name" name="name" type="text" required autofocus autocomplete="name" maxlength="40"
                       value="{{ old('name') }}" class="field">
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="field-label">Correo</label>
                <input id="email" name="email" type="email" required autocomplete="email"
                       value="{{ old('email') }}" class="field">
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="field-label">Contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" class="field">
                <p class="mt-1 text-sm text-ash-muted">Al menos 8 caracteres.</p>
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="field-label">Repite la contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="field">
            </div>

            <button type="submit" class="btn w-full">Crear cuenta</button>
        </form>

        <p class="mt-6 text-center text-sm text-ash-muted">
            ¿Ya tienes cuenta? <a href="{{ route('login') }}" class="text-hazard underline">Entra</a>
        </p>
    </div>
</x-layout>
