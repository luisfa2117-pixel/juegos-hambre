{{--
    Esta página NO usa el layout a propósito: si algo grave ha fallado (por ejemplo, la base de datos),
    el layout también podría fallar al buscar al usuario. Por eso es HTML sencillo, con los estilos aquí mismo.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Algo ha fallado | Juegos del Hambre</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #1d2229; color: #e6e2d8;
               font-family: system-ui, sans-serif; padding: 1rem; }
        main { max-width: 32rem; }
        p.code { font-size: 4rem; font-weight: 800; color: #f2b134; margin: 0; }
        a { display: inline-block; margin-top: 1.5rem; padding: .6rem 1rem; background: #f2b134; color: #1d2229;
            font-weight: 600; text-decoration: none; border-radius: 2px; }
    </style>
</head>
<body>
    <main>
        <p class="code">500</p>
        <h1>Algo ha fallado en el servidor</h1>
        <p>No es culpa tuya. Prueba otra vez dentro de un rato.</p>
        <a href="/">Volver al inicio</a>
    </main>
</body>
</html>
