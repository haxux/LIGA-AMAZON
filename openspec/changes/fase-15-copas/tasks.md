# Tasks: Fase 15 — Copas

**Estado: terminado.** Línea base 497 tests; al cerrar, **542 en verde**.

**Rama**: `fase-15/1-copas`

## Unidad 1 — El modelo (TDD)

- [x] 1.1 RED: un partido pertenece a UNA competición — jornada de liga, cruce de copa o
      grupo de copa—, y el modelo rechaza el que no pertenezca a ninguna o a dos.
- [x] 1.2 Migración: `cups` (temporada, nombre, si lleva grupos), `cup_teams` (participantes,
      con su grupo si lo hay), `cup_groups`, `cup_rounds` (nombre, orden, partidos por
      eliminatoria) y `cup_ties` (los dos equipos, ganador y motivo de la decisión).
- [x] 1.3 Migración: `games` gana `cup_tie_id` y `cup_group_id`, y `matchday_id` pasa a
      admitir nulos. **CONSTRAINT**: la vuelta atrás borra los partidos de copa antes de
      volver a hacer obligatoria la columna, en vez de fallar a medias.
- [x] 1.4 Modelos y factories, con el guard de 1.1.

## Unidad 2 — Lo que se deriva (TDD)

- [x] 2.1 RED: el global de una eliminatoria suma sus partidos; gana quien marque más; si
      empatan, la eliminatoria queda pendiente de decisión y nadie pasa solo.
- [x] 2.2 RED: la decisión del administrador nombra al que pasa y guarda el motivo.
- [x] 2.3 `StandingsService` aprende a construir una tabla con un conjunto de partidos dado,
      para que un grupo de copa se clasifique con el mismo código que una división.

## Unidad 3 — El panel del administrador (TDD)

- [x] 3.1 Recurso de copas: nombre, temporada y formato.
- [x] 3.2 Participantes: equipos de cualquier división de esa temporada, y su grupo cuando lo
      haya.
- [x] 3.3 Rondas y cruces: crear la ronda con sus partidos por eliminatoria, emparejar, y
      resolver el empate diciendo quién pasa y por qué.
- [x] 3.4 Los partidos de un cruce se cargan como los de liga, con sus eventos.

## Unidad 4 — La parte pública (TDD)

- [x] 4.1 Página de la copa: grupos con su tabla y cuadro ronda a ronda, con el resultado de
      cada cruce y quién pasó.
- [x] 4.2 Los partidos de copa aparecen donde ya se miran partidos: la página de Partidos y la
      pestaña del club.
- [x] 4.3 El detalle de un partido dice a qué competición pertenece.

## Unidad 5 — Estadísticas por competición (TDD)

- [x] 5.1 Goleadores, asistentes y porterías a cero, filtrables por liga, copa o total.
- [x] 5.2 Las cifras de un club y las de un jugador, por competición.

## Unidad 6 — Especificación y cierre

- [x] 6.1 Deltas en `league-data-model`, `admin-league-crud` y `public-views`.
- [x] 6.2 Suite en verde y Pint limpio.
- [x] 6.3 Marcar tareas y anotar lo que queda por probar a mano.

## Lo que queda por probar a mano

Nada de esto se puede afirmar desde la suite, y el propietario lo verifica en su navegador:

- [ ] Crear una copa en `/admin`, apuntar equipos de **dos divisiones distintas** y
      comprobar que el cruce entre ellos se deja crear: es justo lo que una división no
      sabe hacer.
- [ ] Una ronda a dos partidos que acabe empatada: comprobar que el cuadro la deja
      **pendiente**, que el botón de decidir pide equipo y motivo, y que el motivo sale
      publicado en `/copas/{copa}`.
- [ ] Cargar un gol en un partido de copa y verlo aparecer en `/estadisticas` con el filtro
      en «Todo» y en esa copa, y **no** con el filtro en «Liga».
- [ ] Un club sin copa: su pestaña Stats tiene que seguir igual que siempre, con sus puntos
      y sin selector.

## Lo que este cambio arregla de paso

- Las estadísticas preguntaban la temporada de un partido por su **jornada**, y un partido
  de copa no tiene ninguna: sin esto, los goles y las tarjetas de copa no habrían aparecido
  en ninguna pantalla del sitio. `Game::scopeInSeason()` es lo que cierra ese agujero.
- Las cifras de un club iban a medias: los goles de copa ya sumaban —salen del marcador—
  mientras sus tarjetas no.
- Los buscadores de equipo por nombre reventaban con `Unknown column 'name'` desde la Fase 9,
  latente hasta que alguien buscó «COPA» en Partidos (commit `207080f`).
