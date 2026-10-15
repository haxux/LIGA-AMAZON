<?php

namespace App\Models;

use Database\Factories\SuggestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * Una idea de un técnico para mejorar el proyecto.
 *
 * La escribe un técnico desde su panel y la lee el administrador, que la marca
 * como leída o aplicada. Cualquier técnico puede mandar las que quiera; sólo
 * ve las suyas.
 */
#[Fillable(['user_id', 'title', 'body', 'status'])]
class Suggestion extends Model
{
    /** @use HasFactory<SuggestionFactory> */
    use HasFactory;

    public const STATUS_NEW = 'nueva';

    public const STATUS_READ = 'leida';

    public const STATUS_DONE = 'aplicada';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_NEW => 'Nueva',
        self::STATUS_READ => 'Leída',
        self::STATUS_DONE => 'Aplicada',
    ];

    /**
     * En memoria y no sólo como valor por omisión de la columna, por la misma
     * razón que en `Transfer`: el guard de abajo lee `status` justo al crear.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_NEW,
    ];

    protected static function booted(): void
    {
        static::saving(function (Suggestion $suggestion): void {
            if (! array_key_exists($suggestion->status, self::STATUSES)) {
                throw ValidationException::withMessages(['status' => "El estado {$suggestion->status} no existe."]);
            }

            if (blank($suggestion->title) || blank($suggestion->body)) {
                throw ValidationException::withMessages(['body' => 'Una sugerencia necesita título y texto.']);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
