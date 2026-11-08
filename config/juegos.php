<?php

/*
 * Configuración propia del simulador. Se lee con config('juegos.algo').
 * Lo que depende de dónde se ejecuta (tu ordenador o el servidor) sale del .env.
 */
return [

    // ¿Se puede crear una cuenta con correo y contraseña? En producción, false: solo invitados
    'registration' => env('REGISTRATION_ENABLED', true),

    // Días sin entrar tras los que se borra una cuenta de invitado (comando guests:prune)
    'guest_days' => 30,

    // Máximos por cuenta, para que nadie llene la base de datos
    'limits' => [
        'characters' => 30,
        'games' => 50,
    ],

    // Para la página "Acerca del proyecto" y el pie de página
    'author' => env('JUEGOS_AUTHOR', 'Tu nombre'),
    'contact' => env('JUEGOS_CONTACT', 'tu-correo@ejemplo.com'),
    'repository' => env('JUEGOS_REPOSITORY', 'https://github.com/TU_USUARIO/juegos-hambre'),

];
