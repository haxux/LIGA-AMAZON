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

## Unidad 2 — Rol y cuentas (TDD)

- [ ] 2.1 RED: un usuario con rol `tecnico` no puede abrir `/admin`; uno con rol `admin` sí.
- [ ] 2.2 RED: un técnico sin club no se guarda (invariante de modelo, estilo `Game::booted()`).
- [ ] 2.3 Migración: `users.role`, `users.club_id`, `users.username` (ver O1 de la propuesta).
      Los usuarios existentes quedan como `admin`.
- [ ] 2.4 `User::canAccessPanel()` decide por panel: `admin` sólo para el rol admin, `club`
      sólo para el técnico.
- [ ] 2.5 Panel: recurso de usuarios en `/admin` para crear técnicos (usuario, contraseña,
      club), con el club obligatorio cuando el rol es técnico.

## Unidad 3 — Políticas por registro (TDD)

- [ ] 3.1 RED: un técnico no puede editar un jugador de otro club, ni por URL directa.
- [ ] 3.2 Políticas para `Team`, `Player` y cuanto cuelgue del club; el admin pasa siempre.
- [ ] 3.3 Cerrar el pendiente 4.1 de `DESPLIEGUE.md`: reescribirlo como resuelto, con lo que
      cubre y lo que no.

## Unidad 4 — Panel `/club` y acceso desde la web pública

- [ ] 4.1 Panel Filament `club`, en español, con el club del técnico fijado por sesión.
- [ ] 4.2 Acceso desde el sitio público y escudo arriba a la derecha cuando hay sesión.
- [ ] 4.3 RED: sin sesión, la cabecera pública no muestra ni enlace ni escudo, y ninguna
      página pública cambia respecto de hoy.

## Fases siguientes (esqueleto)

- **Fase 10** — Plantilla (posición específica, once ideal), Trofeos, Mis Enfrentamientos.
- **Fase 11** — Equipos en la parte pública: listado y ficha con sus seis pestañas.
- **Fase 12** — Contabilidad: valores, presupuesto, fichajes, ventas y préstamos.
- **Fase 13** — Chat y ofertas.
