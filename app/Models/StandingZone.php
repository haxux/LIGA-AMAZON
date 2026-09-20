<?php

namespace App\Models;

use Database\Factories\StandingZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['division_id', 'label', 'color', 'from_position', 'to_position'])]
class StandingZone extends Model
{
    /** @use HasFactory<StandingZoneFactory> */
    use HasFactory;

    /**
     * A closed palette rather than a free colour picker: these swatches are
     * chosen against the table's dark surface, and a hex typed by hand is one
     * `#333` away from a band nobody can see. Stored by key, so restyling the
     * site never means rewriting rows.
     *
     * @var array<string, array{label: string, hex: string}>
     */
    public const COLORS = [
        'green' => ['label' => 'Green', 'hex' => '#2FA36B'],
        'blue' => ['label' => 'Blue', 'hex' => '#4A8FE0'],
        'red' => ['label' => 'Red', 'hex' => '#D1554E'],
        'amber' => ['label' => 'Amber', 'hex' => '#FFB800'],
        'purple' => ['label' => 'Purple', 'hex' => '#9B7BE0'],
        'grey' => ['label' => 'Grey', 'hex' => '#8A8A8A'],
    ];

    protected function casts(): array
    {
        return [
            'from_position' => 'integer',
            'to_position' => 'integer',
        ];
    }

    /**
     * Entity invariants, enforced here for the same reason as Game's and
     * Matchday's guards — every write path goes through Eloquent:
     *
     *  - a band runs downwards (1st is above 4th), so to_position may not sit
     *    above from_position;
     *  - a position belongs to at most one band, or the table would have to
     *    choose a colour for it.
     *
     * NOTE: WithoutModelEvents mutes this during seeding (design D3/D9).
     */
    protected static function booted(): void
    {
        static::saving(function (StandingZone $zone): void {
            if ($zone->to_position < $zone->from_position) {
                throw ValidationException::withMessages([
                    'to_position' => 'The last position cannot be above the first one.',
                ]);
            }

            $overlapping = static::query()
                ->where('division_id', $zone->division_id)
                ->when($zone->exists, fn ($query) => $query->whereKeyNot($zone->getKey()))
                ->where('from_position', '<=', $zone->to_position)
                ->where('to_position', '>=', $zone->from_position)
                ->first();

            if ($overlapping !== null) {
                throw ValidationException::withMessages([
                    'from_position' => "Positions {$overlapping->from_position}–{$overlapping->to_position} already belong to “{$overlapping->label}”.",
                ]);
            }
        });
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function covers(int $position): bool
    {
        return $position >= $this->from_position && $position <= $this->to_position;
    }

    /**
     * Unknown keys fall back to grey rather than rendering an empty style
     * attribute: a row banded with nothing looks like a bug to a visitor.
     */
    public function hex(): string
    {
        return self::COLORS[$this->color]['hex'] ?? self::COLORS['grey']['hex'];
    }
}
