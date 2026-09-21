# Tasks: Fase 15 — Copas

**Estado: en curso.** Línea base 497 tests.

**Rama**: `fase-15/1-copas`

## Unidad 1 — El modelo (TDD)

- [ ] 1.1 RED: un partido pertenece a UNA competición — jornada de liga, cruce de copa o
      grupo de copa—, y el modelo rechaza el que no pertenezca a ninguna o a dos.
- [ ] 1.2 Migración: `cups` (temporada, nombre, si lleva grupos), `cup_teams` (participantes,
      con su grupo si lo hay), `cup_groups`, `cup_rounds` (nombre, orden, partidos por
      eliminatoria) y `cup_ties` (los dos equipos, ganador y motivo de la decisión).
- [ ] 1.3 Migración: `games` gana `cup_tie_id` y `cup_group_id`, y `matchday_id` pasa a
      admitir nulos. **CONSTRAINT**: la vuelta atrás borra los partidos de copa antes de
      volver a hacer obligatoria la columna, en vez de fallar a medias.
- [ ] 1.4 Modelos y factories, con el guard de 1.1.

## Unidad 2 — Lo que se deriva (TDD)

- [ ] 2.1 RED: el global de una eliminatoria suma sus partidos; gana quien marque más; si
      empatan, la eliminatoria queda pendiente de decisión y nadie pasa solo.
- [ ] 2.2 RED: la decisión del administrador nombra al que pasa y guarda el motivo.
- [ ] 2.3 `StandingsService` aprende a construir una tabla con un conjunto de partidos dado,
      para que un grupo de copa se clasifique con el mismo código que una división.

## Unidad 3 — El panel del administrador (TDD)

- [ ] 3.1 Recurso de copas: nombre, temporada y formato.
- [ ] 3.2 Participantes: equipos de cualquier división de esa temporada, y su grupo cuando lo
      haya.
- [ ] 3.3 Rondas y cruces: crear la ronda con sus partidos por eliminatoria, emparejar, y
      resolver el empate diciendo quién pasa y por qué.
- [ ] 3.4 Los partidos de un cruce se cargan como los de liga, con sus eventos.

## Unidad 4 — La parte pública (TDD)

- [ ] 4.1 Página de la copa: grupos con su tabla y cuadro ronda a ronda, con el resultado de
      cada cruce y quién pasó.
- [ ] 4.2 Los partidos de copa aparecen donde ya se miran partidos: la página de Partidos y la
      pestaña del club.
- [ ] 4.3 El detalle de un partido dice a qué competición pertenece.

## Unidad 5 — Estadísticas por competición (TDD)

- [ ] 5.1 Goleadores, asistentes y porterías a cero, filtrables por liga, copa o total.
- [ ] 5.2 Las cifras de un club y las de un jugador, por competición.

## Unidad 6 — Especificación y cierre

- [ ] 6.1 Deltas en `league-data-model`, `admin-league-crud` y `public-views`.
- [ ] 6.2 Suite en verde y Pint limpio.
- [ ] 6.3 Marcar tareas y anotar lo que queda por probar a mano.
