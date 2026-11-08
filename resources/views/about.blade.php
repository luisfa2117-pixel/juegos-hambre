<x-layout title="Acerca del proyecto">
    <div class="max-w-2xl space-y-10">
        <section>
            <h1 class="font-display text-4xl">Acerca del proyecto</h1>
            <p class="mt-4">
                Este simulador es un proyecto personal, sin ánimo de lucro, hecho por {{ config('juegos.author') }}
                para aprender Laravel. Está inspirado en los libros de Los Juegos del Hambre y en el simulador de Brantsteele,
                pero todo su contenido (eventos, personajes, distritos) es original.
            </p>
            <p class="mt-3">
                El código es abierto y está en <a href="{{ config('juegos.repository') }}" class="text-hazard underline">GitHub</a>.
                Para cualquier cosa, puedes escribir a <a href="mailto:{{ config('juegos.contact') }}" class="text-hazard underline">{{ config('juegos.contact') }}</a>.
            </p>
        </section>

        <section>
            <h2 class="font-condensed text-2xl font-semibold">Privacidad</h2>

            <h3 class="mt-4 font-semibold">Qué se guarda</h3>
            <p class="mt-1">
                Si pruebas el simulador sin registrarte, se crea una cuenta de invitado con un nombre al azar
                («Invitado 1234»). No se te pide ni se guarda ningún dato personal: ni correo, ni nombre, ni contraseña.
                Solo se guardan los tributos que crees (con sus fotos, si les pones), tus partidas y cuándo entraste por última vez.
            </p>

            <h3 class="mt-4 font-semibold">Cuánto tiempo</h3>
            <p class="mt-1">
                Tu cuenta de invitado se borra, con todo lo que contiene, cuando pulsas «Salir» o si no la usas durante
                {{ config('juegos.guest_days') }} días.
            </p>

            <h3 class="mt-4 font-semibold">Cookies</h3>
            <p class="mt-1">
                Solo se usan las cookies técnicas necesarias para que la web funcione: la de la sesión, la que protege
                los formularios y la que te recuerda en este navegador para que no pierdas tus partidas.
                No hay analítica, ni publicidad, ni contenido de otras webs: las fuentes y todo lo demás se sirven desde aquí.
            </p>
        </section>
    </div>
</x-layout>
