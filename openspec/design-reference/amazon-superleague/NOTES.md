# Referencia de frontend — Amazon Superleague

> Fuente: `Página de liga de fútbol.zip` (mockup interactivo exportado de una
> herramienta de diseño/prototipado — usa un runtime propio `support.js` con
> tags custom `<x-dc>`, `<sc-for>` y una clase `DCLogic`). **No es HTML/CSS
> plano ni Blade** — no se copia tal cual, se usa como referencia visual y de
> estructura para las Fases 5 y 6 del roadmap (vistas públicas + pulido).

## Sistema de diseño extraído

**Paleta:**
| Token | Valor | Uso |
|---|---|---|
| Acento (amarillo) | `#FFB800` | marca, PTS, CTAs, bordes activos |
| Fondo oscuro | `#1B1B1B` | header, footer, secciones oscuras |
| Superficie | `#2B2B2B` / `#262626` / `#383838` | cards, tabla, placeholders de imagen |
| Éxito (form W) | `#1F7A4D` / `#6FE0A8` | chip de victoria, indicador "en vivo" |
| Neutro (form D) | `#5C5C5C` | chip de empate |
| Peligro (form L / descenso) | `#8A3B3B` | chip de derrota, zona de descenso |
| Texto sobre oscuro | `#ffffff` / `rgba(255,255,255,0.6-0.72)` | texto primario/secundario |

**Tipografía** (Google Fonts):
- `Barlow Condensed` (500/600/700/800) — títulos, nombres de equipo, botones, labels UI
- `Barlow` (400/500/600/700) — cuerpo de texto
- `IBM Plex Mono` (400/500) — metadatos (fechas, "JORNADA X", labels técnicos tipo PJ/DG/PTS)

**Patrón visual recurrente:** fondo amarillo con textura de líneas diagonales
sutiles (`repeating-linear-gradient`) detrás del contenido; cards oscuras
(`#2B2B2B`) con `border-radius: 6px` flotando sobre el amarillo.

## Secciones del mockup → mapeo a Fases del roadmap

| Sección del mockup | Entidad/dato que necesita | Fase que lo cubre |
|---|---|---|
| Header + nav | — (estático) | Fase 5 |
| Hero + noticias destacadas | **News** (no existe en el modelo de dominio) | ⚠️ fuera de alcance actual |
| Tabla de posiciones (con tabs Primera/Segunda) | `Standing` (derivado), `Season` con múltiples divisiones | Fase 4/5 — **el modelo actual no tiene "división" dentro de `Season`, revisar** |
| Forma reciente (chips G/E/P) | últimos N resultados de `Game` por `Team` | Fase 4/5 (cálculo adicional, no solo tabla) |
| Partido de la semana | `Game` destacado + estado "en vivo" | Fase 5 — "en vivo" no existe como concepto en el dominio actual |
| Líderes (goleadores/asistencias) | **estadísticas de `Player`** (goles, asistencias) | ⚠️ fuera de alcance actual — `Player` no tiene stats agregadas |
| Fixtures (próximos/resultados) | `Game` + `Matchday` | Fase 5 |
| Fantasy Superleague | — | ⚠️ fuera de alcance total, no mencionado en ARQUITECTURA.md |
| Noticias / cantera / fichajes | **News** | ⚠️ fuera de alcance actual |
| Footer | — (estático) | Fase 5 |

## Gaps de scope a decidir antes de Fase 5

1. **Noticias/Fichajes**: el mockup le da mucho peso visual a una sección de
   noticias que no existe en el modelo de dominio (`ARQUITECTURA.md` §4).
   ¿Se agrega una entidad `News`/`Article` en un Fase futura, o se omite esa
   sección del diseño para el demo?
2. **Goleadores/Asistencias**: requiere estadísticas agregadas por jugador
   (goles, asistencias) que hoy no se registran — `Player` solo tiene datos
   básicos, no hay tabla de eventos de partido (goles, tarjetas).
3. **Divisiones (Primera/Segunda)**: el mockup asume dos divisiones con tabla
   propia cada una. `ARQUITECTURA.md` no menciona subdivisión dentro de
   `Season` — si el demo es de una sola división, esta parte del diseño se
   simplifica.
4. **Fantasy Superleague**: sin mención en ARQUITECTURA.md — tratar como
   fuera de alcance salvo que se decida lo contrario.

## Archivos en esta carpeta

- `Amazon Superleague.dc.html` — mockup original (referencia, no se ejecuta en Laravel)
- `support.js` — runtime del mockup (no se usa en el proyecto)
- `uploads/pasted-1785857503738-0.png` — asset original del export
