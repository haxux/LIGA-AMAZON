<?php

namespace Tests\Unit;

use App\Models\Lineup;
use PHPUnit\Framework\TestCase;

class LineupFormationsTest extends TestCase
{
    public function test_every_formation_fields_ten_outfield_players_and_a_goalkeeper(): void
    {
        foreach (Lineup::FORMATIONS as $name => $lines) {
            $this->assertSame(10, array_sum($lines), "{$name} no suma 10 jugadores de campo.");
            $this->assertSame($name, implode('-', $lines), "El nombre {$name} no coincide con sus líneas.");
            $this->assertSame(range(1, Lineup::SLOTS), array_merge(...Lineup::rowsFor($name)));
        }
    }
}
