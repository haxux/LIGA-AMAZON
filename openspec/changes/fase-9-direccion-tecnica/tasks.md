# Tasks: Fase 9 — Entidad Club y rol Director Técnico

Las fases 10 a 13 recibirán sus tareas cuando les toque; aquí sólo se detalla la 9, y al final
queda el esqueleto de las siguientes para que el orden no se pierda.

**Rama**: `fase-9/1-club-y-rol-tecnico`

## Precondición

- [ ] 0.1 Suite en verde antes de tocar nada: `docker compose exec -T app php artisan test`
      debe reportar **254/254**. Un fallo previo hay que conocerlo ahora, no a mitad.

## Unidad 1 — La entidad Club (TDD)

- [ ] 1.1 RED: test de esquema — `clubs` con `name` único, `short_name`, `crest_path`,
      `founded_year`; `teams` con `club_id` y `unique(season_id, club_id)`.
- [ ] 1.2 RED: test de migración de datos — partiendo de equipos con nombres repetidos entre
      temporadas, queda **un** club por nombre y cada `teams.club_id` apunta al suyo.
- [ ] 1.3 Migración: crear `clubs`, añadir `club_id` a `teams`, rellenar, mover las cuatro
      columnas y rehacer los índices únicos.
      **CONSTRAINT**: el índice nuevo entra antes de que salga el viejo, igual que en
      `add_division_id_to_matchdays`: en MySQL el índice viejo sostiene la FK de `season_id`
      y soltarlo primero falla con errno 1553.
- [ ] 1.4 Modelos: `Club` con `teams()`, `Team` con `club()` y los accesores que delegan
      (`name`, `short_name`, `crest_path`, `founded_year`).
- [ ] 1.5 Factories y seeder: `ClubFactory`; `TeamFactory` crea o reutiliza su club.
- [ ] 1.6 GREEN: la suite entera pasa **sin modificar los tests que leen `$team->name`** — si
      alguno hay que tocarlo, el accesor de 1.4 está mal.
- [ ] 1.7 Panel: recurso `Club` en `/admin`; `TeamResource` pasa a elegir club en lugar de
      escribir nombre y escudo.
- [ ] 1.8 Probar la migración contra un TiDB desechable (`./scripts/test-tidb.sh`) antes de
      dar la unidad por buena.

## Unidad 2 — Plantillas y estadios al club (TDD)

- [ ] 2.1 RED: test de esquema — `players.club_id` con `unique(club_id, shirt_number)` y
      `stadiums.club_id` único; ninguna de las dos conserva `team_id`.
- [ ] 2.2 RED: test de migración de datos — cada jugador y cada estadio quedan apuntando al
      club del equipo en el que estaban, sin perder ninguna fila.
- [ ] 2.3 Migración: añadir `club_id` a ambas, rellenar desde `teams.club_id`, rehacer índices
      y soltar `team_id`.
      **CONSTRAINT**: mismo orden de índices que en la unidad 1, y `stadiums.team_id` es
      `unique()`, así que su índice también sostiene la FK.
- [ ] 2.4 Modelos: `Player::club()` y `Stadium::club()`; `Club::players()` y `Club::stadium()`.
      `Player::team()` se elimina, no se disfraza — aquí el significado cambia (design D1b).
- [ ] 2.5 Actualizar los tres puntos que leían `player->team`: `GoalscorersService`, la lista
      pública de goleadores y la etiqueta del selector en el gestor de eventos.
- [ ] 2.6 Panel: el gestor de jugadores se mueve de `TeamResource` a `ClubResource`, igual que
      el de estadio. El selector de jugadores de un partido pasa a filtrar por los clubes de
      los dos equipos.
- [ ] 2.7 GREEN: suite entera, con los tests de jugadores y estadios actualizados al club.

## Unidad 3 — Rol y cuentas (TDD)

- [ ] 3.1 RED: un usuario con rol `tecnico` no puede abrir `/admin`; uno con rol `admin` sí.
- [ ] 3.2 RED: un técnico sin club no se guarda (invariante de modelo, estilo `Game::booted()`).
- [ ] 3.3 Migración: `users.role` y `users.club_id`. Sin `username`: se entra por correo
      (decisión del propietario). Los usuarios existentes quedan como `admin`.
- [ ] 3.4 `User::canAccessPanel()` decide por panel: `admin` sólo para el rol admin, `club`
      sólo para el técnico.
- [ ] 3.5 Panel: recurso de usuarios en `/admin` para crear técnicos (nombre, correo,
      contraseña y club), con el club obligatorio cuando el rol es técnico. El nombre es el
      que saldrá en la ficha pública.

## Unidad 4 — Políticas por registro (TDD)

- [ ] 4.1 RED: un técnico no puede editar un jugador de otro club, ni por URL directa.
- [ ] 4.2 Políticas para `Club`, `Team`, `Player` y cuanto cuelgue del club; el admin pasa
      siempre.
- [ ] 4.3 Cerrar el pendiente 4.1 de `DESPLIEGUE.md`: reescribirlo como resuelto, con lo que
      cubre y lo que no.

## Unidad 5 — Panel `/club` y acceso desde la web pública

- [ ] 5.1 Panel Filament `club`, en español, con el club del técnico fijado por sesión.
- [ ] 5.2 Acceso desde el sitio público y escudo arriba a la derecha cuando hay sesión.
- [ ] 5.3 RED: sin sesión, la cabecera pública no muestra ni enlace ni escudo, y ninguna
      página pública cambia respecto de hoy.

## Fases siguientes (esqueleto)

- **Fase 10** — Plantilla (posición específica, once ideal), Trofeos, Mis Enfrentamientos.
- **Fase 11** — Equipos en la parte pública: listado y ficha con sus seis pestañas.
- **Fase 12** — Contabilidad: valores, presupuesto, fichajes, ventas y préstamos.
- **Fase 13** — Chat y ofertas.
