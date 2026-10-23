<?php

namespace App\Services;

use App\Models\User;
use App\Models\WallImage;
use App\Models\WallPost;
use App\Models\WallReaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * «A Segunda», el foro interno: publicar, responder, reaccionar y borrar
 * (decisión del propietario, 2026-10-23).
 */
final class WallService
{
    /** El lado mayor de una foto guardada: de sobra para verla en un móvil. */
    public const IMAGE_MAX_SIDE = 1600;

    /** Publicaciones (y respuestas) por persona y hora: que nadie inunde el muro. */
    public const PER_HOUR = 20;

    public function __construct(private readonly PushNotificationService $push) {}

    /**
     * @param  array<int, UploadedFile>  $images
     */
    public function publish(User $author, ?string $body, array $images = [], ?WallPost $parent = null): WallPost
    {
        $body = trim((string) $body);

        if ($body === '' && $images === []) {
            throw ValidationException::withMessages(['body' => 'Escribe algo o añade una foto.']);
        }

        $key = 'wall-post:'.$author->getKey();

        if (RateLimiter::tooManyAttempts($key, self::PER_HOUR)) {
            throw ValidationException::withMessages(['body' => 'Has publicado mucho en poco tiempo. Prueba en un rato.']);
        }

        // Una respuesta cuelga de la publicación, nunca de otra respuesta: el
        // hilo es de un solo nivel, como se lee en un móvil.
        $parent = $parent?->isReply() ? $parent->parent : $parent;

        $stored = [];

        try {
            $post = DB::transaction(function () use ($author, $body, $images, $parent, &$stored): WallPost {
                $post = WallPost::create([
                    'user_id' => $author->getKey(),
                    'parent_id' => $parent?->getKey(),
                    'body' => $body === '' ? null : $body,
                ]);

                foreach (array_values($images) as $position => $image) {
                    [$path, $width, $height] = $this->storeImage($image);
                    $stored[] = $path;

                    WallImage::create([
                        'wall_post_id' => $post->getKey(),
                        'path' => $path,
                        'width' => $width,
                        'height' => $height,
                        'position' => $position,
                    ]);
                }

                return $post;
            });
        } catch (\Throwable $exception) {
            // Si algo falla a mitad, las fotos ya escritas no se quedan huérfanas.
            Storage::disk(config('filesystems.uploads'))->delete($stored);

            throw $exception;
        }

        RateLimiter::hit($key, 3600);

        if ($parent !== null) {
            $this->push->wallReply($post);
        }

        return $post;
    }

    /**
     * Pulsar la misma reacción otra vez la quita; pulsar la contraria la
     * cambia. Devuelve cómo queda: 1, -1 o 0.
     */
    public function react(User $user, WallPost $post, int $value): int
    {
        $value = $value > 0 ? WallReaction::LIKE : WallReaction::DISLIKE;

        $current = WallReaction::query()->where('wall_post_id', $post->getKey())->where('user_id', $user->getKey())->first();

        if ($current?->value === $value) {
            $current->delete();

            return 0;
        }

        WallReaction::query()->updateOrCreate(
            ['wall_post_id' => $post->getKey(), 'user_id' => $user->getKey()],
            ['value' => $value],
        );

        return $value;
    }

    /** Lo suyo, cada uno; cualquier cosa, el presidente, que modera. */
    public function mayDelete(User $user, WallPost $post): bool
    {
        return (int) $post->user_id === (int) $user->getKey() || $user->isAdmin();
    }

    /**
     * La foto, reducida y vuelta a codificar en JPEG: así sale ligera y SIN
     * sus datos ocultos (EXIF), entre ellos la ubicación GPS que guardan las
     * de móvil. Antes se endereza, porque la orientación de un móvil va en
     * esos mismos datos y al quitarlos la foto quedaría de lado.
     *
     * @return array{0: string, 1: int, 2: int}  ruta, ancho y alto
     */
    private function storeImage(UploadedFile $file): array
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw ValidationException::withMessages(['images' => 'Una de las fotos no se pudo leer.']);
        }

        $source = $this->upright($source, $file);

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::IMAGE_MAX_SIDE / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        // Sobre blanco: una PNG con transparencia saldría negra en JPEG.
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 82);
        $jpeg = (string) ob_get_clean();

        $path = 'wall/'.Str::ulid()->toBase32().'.jpg';
        Storage::disk(config('filesystems.uploads'))->put($path, $jpeg, 'public');

        return [$path, $newWidth, $newHeight];
    }

    /** La foto derecha según la orientación que apuntó la cámara. */
    private function upright(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        return $rotated ?: $image;
    }
}
