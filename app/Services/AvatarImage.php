<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Prepara la foto de un personaje: la recorta en cuadrado, la reduce a 256 × 256 y la guarda en WebP.
 * Una foto de móvil de 2 MB se queda en unos 15-30 KB, y se ve igual de bien en un círculo de 80 píxeles.
 * Usa GD, la extensión de imágenes que ya trae PHP.
 */
class AvatarImage
{
    public const SIZE = 256;

    /** Devuelve la ruta donde la ha guardado, dentro del disco "public" */
    public function store(UploadedFile $file): string
    {
        $source = imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        $source = $this->fixOrientation($source, $file);

        // Recorte cuadrado desde el centro
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $avatar = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled(
            $avatar, $source,
            0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2),
            self::SIZE, self::SIZE, $side, $side,
        );

        // imagewebp() escribe la imagen; con ob_start la capturamos en una variable en vez de en un fichero
        ob_start();
        function_exists('imagewebp') ? imagewebp($avatar, null, 80) : imagejpeg($avatar, null, 85);
        $contents = (string) ob_get_clean();

        $path = 'characters/'.Str::random(40).(function_exists('imagewebp') ? '.webp' : '.jpg');
        Storage::disk('public')->put($path, $contents);

        return $path;
    }

    /**
     * Las fotos de móvil guardan "estoy girada" en sus datos EXIF en vez de girar los píxeles.
     * Si no lo corregimos, algunas salen tumbadas.
     */
    private function fixOrientation(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || $file->getMimeType() !== 'image/jpeg') {
            return $image;
        }

        $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }
}
