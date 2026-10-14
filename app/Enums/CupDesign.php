<?php

namespace App\Enums;

/**
 * El diseño de una copa internacional, tomado de la competición real que
 * evoca. Sólo la paleta vive aquí; el logo y el fondo los sube el
 * administrador con la copa.
 *
 * Los colores se aplican redefiniendo los tokens del sitio (`--color-brand`,
 * `--color-surface*`) sobre el contenedor de la copa, así que cualquier
 * componente que ya los use —tarjetas de partido, tablas— se tiñe solo.
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
     * @return array{brand: string, surface: string, alt: string, muted: string, hero: string}
     */
    public function palette(): array
    {
        return match ($this) {
            // Azul noche y oro: el trofeo y el protocolo de la noche de martes.
            self::Libertadores => [
                'brand' => '#D6AE4B',
                'surface' => '#101F3D',
                'alt' => '#0A1730',
                'muted' => '#1B2E55',
                'hero' => 'linear-gradient(135deg, #07122A 0%, #14284F 60%, #2A2210 130%)',
            ],
            // Azul profundo con verde lima: más joven y más fresca.
            self::Sudamericana => [
                'brand' => '#8FD14F',
                'surface' => '#0F2B3A',
                'alt' => '#0A2230',
                'muted' => '#18414F',
                'hero' => 'linear-gradient(135deg, #061B26 0%, #0E3B45 60%, #1D3A12 130%)',
            ],
        };
    }
}
