<?php

namespace App\Enums;

/**
 * El diseño de una copa internacional, tomado de la competición real que
 * evoca. Aquí viven la paleta y el nombre del fondo; el dibujo del fondo está
 * en resources/css/app.css (`.cup-stage-*`), y el logo y la imagen de cabecera
 * los sube el administrador con la copa.
 *
 * Al entrar en una de estas copas cambia todo el contenido de la página —no la
 * cabecera ni el pie—: el layout envuelve el `<main>` en un escenario que
 * redefine los tokens de color del sitio (`--color-brand`, `--color-ink`,
 * `--color-surface*`). Cualquier componente que ya los use se tiñe solo.
 */
enum CupDesign: string
{
    case Libertadores = 'libertadores';
    case Sudamericana = 'sudamericana';

    public function label(): string
    {
        return match ($this) {
            self::Libertadores => 'Copa Libertadores',
            self::Sudamericana => 'Copa Sudamericana',
        };
    }

    /**
     * Las superficies son semitransparentes a propósito: dejan ver el fondo
     * por debajo de tablas y tarjetas, como en las webs oficiales.
     *
     * @return array{brand: string, ink: string, surface: string, alt: string, muted: string}
     */
    public function palette(): array
    {
        return match ($this) {
            // Oro viejo sobre un degradado de bronce a negro: los arcos
            // dorados del trofeo y la noche de la Libertadores.
            self::Libertadores => [
                'brand' => '#E2AA45',
                'ink' => '#F5E8CC',
                'surface' => 'rgba(32, 23, 12, 0.86)',
                'alt' => 'rgba(22, 16, 8, 0.9)',
                'muted' => 'rgba(66, 48, 22, 0.92)',
            ],
            // Azul rey con esquirlas y destellos, y el celeste plateado del
            // trofeo como acento.
            self::Sudamericana => [
                'brand' => '#8ED0FF',
                'ink' => '#EAF2FF',
                'surface' => 'rgba(7, 26, 96, 0.8)',
                'alt' => 'rgba(5, 18, 72, 0.86)',
                'muted' => 'rgba(22, 58, 168, 0.9)',
            ],
        };
    }

    /** Los tokens como atributo `style`, para el escenario o una tarjeta suelta. */
    public function style(): string
    {
        $p = $this->palette();

        return sprintf(
            '--color-brand:%s;--color-ink:%s;--color-surface:%s;--color-surface-alt:%s;--color-surface-muted:%s;',
            $p['brand'], $p['ink'], $p['surface'], $p['alt'], $p['muted'],
        );
    }

    /** La clase del fondo dibujado, definida en resources/css/app.css. */
    public function stageClass(): string
    {
        return 'cup-stage-'.$this->value;
    }
}
