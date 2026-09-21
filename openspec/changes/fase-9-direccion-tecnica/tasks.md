# Tasks: Fase 9 — Entidad Club y rol Director Técnico

**Estado: aplicada.** 299 tests en verde (línea base 254), Pint limpio, y las tres
migraciones ensayadas —ida y vuelta— contra una copia restaurada de producción.

Cada fase del bloque recibe sus tareas cuando le toca y se queda aquí debajo, en orden; las
que aún no han empezado siguen como esqueleto al final. La 10 está a continuación.

**Rama**: `fase-9/1-club-y-rol-tecnico`

## Precondición

- [x] 0.1 Suite en verde antes de tocar nada: `docker compose exec -T app php artisan test`
      debe reportar **254/254**. Un fallo previo hay que conocerlo ahora, no a mitad.

## Unidad 1 — La entidad Club (TDD)

- [x] 1.1 RED: test de esquema — `clubs` con `name` único, `short_name`, `crest_path`,
      `founded_year`; `teams` con `club_id` y `unique(season_id, club_id)`.
- [x] 1.2 Verificación de la migración de datos: se hace en el ensayo contra la copia real de
      producción (1.8), no en la suite. Rehacer ese escenario en SQLite exigiría revertir y
      reaplicar migraciones dentro de un test, que es frágil en cuanto llega la siguiente
      migración; la copia de producción es además una prueba más fuerte, con datos reales.
- [x] 1.3 Migración: crear `clubs`, añadir `club_id` a `teams`, rellenar, mover las cuatro
      columnas y rehacer los índices únicos.
      **CONSTRAINT**: el índice nuevo entra antes de que salga el viejo, igual que en
      `add_division_id_to_matchdays`: en MySQL el índice viejo sostiene la FK de `season_id`
      y soltarlo primero falla con errno 1553.
- [x] 1.4 Modelos: `Club` con `teams()`, `Team` con `club()` y los accesores que delegan
      (`name`, `short_name`, `crest_path`, `founded_year`).
- [x] 1.5 Factories y seeder: `ClubFactory`; `TeamFactory` crea o reutiliza su club.
- [x] 1.6 GREEN: la suite entera pasa **sin modificar los tests que leen `$team->name`** — si
      alguno hay que tocarlo, el accesor de 1.4 está mal.
- [x] 1.7 Panel: recurso `Club` en `/admin`; `TeamResource` pasa a elegir club en lugar de
      escribir nombre y escudo.
- [x] 1.8 Probar la migración contra un TiDB desechable (`./scripts/test-tidb.sh`) antes de
      dar la unidad por buena.

## Unidad 2 — Identidad del jugador, pertenencias y estadios (TDD)

- [x] 2.1 RED: test de esquema — `players` con `club_id` y sin `team_id` ni `shirt_number`;
      `squad_memberships` con `team_id`, `player_id`, `shirt_number`, `type` y sus dos índices
      únicos; `stadiums.club_id` único.
- [x] 2.2 RED: test de migración de datos — cada fila de `players` de hoy produce una identidad
      y una pertenencia a su equipo actual, conservando el dorsal; ningún jugador se pierde ni
      se duplica; los estadios quedan en su club.
- [x] 2.3 Migración: crear `squad_memberships`, poblarla desde `players`, añadir `players.club_id`
      desde `teams.club_id`, soltar `team_id` y `shirt_number` de `players`, y mover el estadio.
      **CONSTRAINT**: mismo orden de índices que en la unidad 1; `stadiums.team_id` es
      `unique()`, así que su índice también sostiene la FK.
- [x] 2.4 Modelos: `Player::club()`, `Player::memberships()`, `Team::memberships()`,
      `Club::players()`, `Club::stadium()`, `SquadMembership`. `Player::team()` se elimina:
      aquí el significado cambia y disfrazarlo con un accesor mentiría (design D1b).
- [x] 2.5 RED + verde: observador de `Team::created` que hereda las pertenencias de la
      participación anterior del club, saltándose a los salidos de la liga.
      **CONSTRAINT**: queda mudo bajo `WithoutModelEvents`, como los guards existentes; el
      seeder sigue armando sus plantillas a mano.
- [x] 2.6 Actualizar los tres puntos que leían `player->team`: `GoalscorersService`, la lista
      pública de goleadores y la etiqueta del selector en el gestor de eventos. La etiqueta de
      club sale de la pertenencia de esa temporada, con el club propietario como respaldo.
- [x] 2.7 Panel: el gestor de jugadores pasa a `ClubResource` (identidad) y las pertenencias se
      gestionan desde el equipo de cada temporada; el estadio, a `ClubResource`. El selector de
      jugadores de un partido filtra por las pertenencias de esa temporada.
- [x] 2.8 GREEN: suite entera, con los tests de jugadores y estadios actualizados.

## Unidad 3 — Rol y cuentas (TDD)

- [x] 3.1 RED: un usuario con rol `tecnico` no puede abrir `/admin`; uno con rol `admin` sí.
- [x] 3.2 RED: un técnico sin club no se guarda (invariante de modelo, estilo `Game::booted()`).
- [x] 3.3 Migración: `users.role` y `users.club_id`. Sin `username`: se entra por correo
      (decisión del propietario). Los usuarios existentes quedan como `admin`.
- [x] 3.4 `User::canAccessPanel()` decide por panel: `admin` sólo para el rol admin, `club`
      sólo para el técnico.
- [x] 3.5 Panel: recurso de usuarios en `/admin` para crear técnicos (nombre, correo,
      contraseña y club), con el club obligatorio cuando el rol es técnico. El nombre es el
      que saldrá en la ficha pública.

## Unidad 4 — Políticas por registro (TDD)

- [x] 4.1 RED: un técnico no puede editar un jugador de otro club, ni por URL directa.
- [x] 4.2 Políticas para `Club`, `Team`, `Player` y cuanto cuelgue del club; el admin pasa
      siempre.
- [x] 4.3 Cerrar el pendiente 4.1 de `DESPLIEGUE.md`: reescribirlo como resuelto, con lo que
      cubre y lo que no.

## Unidad 5 — Panel `/club` y acceso desde la web pública

- [x] 5.1 Panel Filament `club`, en español, con el club del técnico fijado por sesión.
- [x] 5.2 Acceso desde el sitio público y escudo arriba a la derecha cuando hay sesión.
- [x] 5.3 RED: sin sesión, la cabecera pública no muestra ni enlace ni escudo, y ninguna
      página pública cambia respecto de hoy.

---

# Tasks: Fase 10 — Plantilla, Trofeos, Mis Enfrentamientos y Once ideal

**Estado: aplicada.** 340 tests en verde (línea base 299, la que dejó la Fase 9), Pint limpio
sobre lo tocado. Sin migraciones de datos: las tres de esta fase sólo añaden
(`players.specific_position`, `trophies`, `lineups` + `lineup_slots`), así que revertirla es
revertir los commits y soltar esas tablas.

**Rama**: `fase-10/1-plantilla-trofeos`

## Unidad 1 — La posición específica (TDD)

- [x] 1.1 RED: `players.specific_position` existe, es opcional, y una específica que no
      pertenece a la general se rechaza al guardar.
- [x] 1.2 Migración aditiva: `specific_position` (4 caracteres, nullable) después de
      `position`. Opcional a propósito: los 29 jugadores que ya están en la liga no tienen
      ninguna, y ponérsela es trabajo del técnico.
- [x] 1.3 Modelo: las 27 posiciones repartidas por posición general en `Player`, y un guard
      en `booted()` que rechaza el cruce. En el modelo y no sólo en el formulario, porque la
      ficha pública agrupa por la general y un portero de extremo la descolocaría.

## Unidad 2 — Plantilla, Trofeos y Mis Enfrentamientos en `/club` (TDD)

- [x] 2.1 RED: cada módulo lista sólo lo del club del técnico, y una URL de otro club da 404
      pasando por el kernel (no con `Livewire::test()`, que se salta el middleware).
- [x] 2.2 `SquadResource`: el técnico ajusta posición general y específica; el nombre queda
      deshabilitado y no hay alta ni baja —eso es del admin, y en la Fase 12 serán fichajes.
      El dorsal se lee de la pertenencia de la temporada vigente.
- [x] 2.3 `TrophyResource` en `/admin` (club, temporada y nombre, sin repetir el mismo trofeo
      dos veces en una temporada) y su gemelo de sólo lectura en `/club`.
- [x] 2.4 `FixtureResource`: los partidos del club, jugados y por jugar. La consulta compara
      por **club** y no por equipo, para que un partido de una temporada anterior siga siendo
      suyo.

## Unidad 3 — Once ideal (TDD)

- [x] 3.1 RED: sólo se elige entre la plantilla de la temporada vigente; cambiar de formación
      conserva a quien sigue cabiendo; un once a medias se guarda; el mismo jugador dos veces
      se rechaza.
- [x] 3.2 Migración aditiva: `lineups` (uno por equipo-temporada) y `lineup_slots` con el
      hueco 1..11. **CONSTRAINT**: se guarda el hueco, nunca coordenadas (design D6).
- [x] 3.3 Página Livewire dentro del panel, no un recurso (design D5).
      **CONSTRAINT**: Livewire reserva `$slots` en un componente; una propiedad pública con
      ese nombre revienta el render con *"Call to a member function getName() on int"*. Las
      elecciones viven en `$picks`.

## Unidad 4 — Los dos cabos sueltos (TDD)

- [x] 4.1 RED: el administrador asigna la posición específica desde `/admin`, las opciones
      siguen a la general y cambiar la general suelta la anterior. La propuesta la quería
      *"editable por el técnico y por el admin"* y sólo estaba hecha la mitad.
- [x] 4.2 Los campos se añaden al `PlayerForm` compartido, así que valen igual en
      `PlayerResource` y en el gestor de jugadores de `ClubResource`. Columna en ambas tablas.
- [x] 4.3 RED + verde: el filtro de jornada de Mis Enfrentamientos filtra por **número** y
      sólo ofrece los números que el club juega. Con `->relationship('matchday', 'number')`
      ofrecía una fila por jornada de cada división y temporada: varias "1" indistinguibles,
      y elegir la equivocada dejaba la tabla vacía.
- [x] 4.4 De paso, dos tests con nombre de temporada al azar: `SeasonFactory` lo saca de un
      año entre 2000 y 2099, así que uno de cada cien choca contra el unique de
      `seasons.name`. Se vio fallar en una corrida completa y se fijaron los nombres.

## Verificación

- [x] V.1 `php artisan test`: 340/340.
- [x] V.2 Pint limpio sobre `app/` y `tests/`. **Nota**: `database/factories/DivisionFactory.php`
      y `NewsFactory.php` fallan `fully_qualified_strict_types` desde antes de esta fase; no
      se tocan aquí para no mezclarlo con lo de la fase.
- [ ] V.3 Probar en el navegador los cuatro módulos con una cuenta de técnico real.
- [ ] V.4 Desplegar. Las tres migraciones son aditivas y el arranque del contenedor las aplica
      solo (`DESPLIEGUE.md` §5.1).

---

# Tasks: Fase 11 — Equipos en la parte pública

**Estado: aplicada.** 369 tests en verde (línea base 340), Pint limpio. Puramente aditiva:
una sección nueva del sitio, sin migraciones. Revertirla es revertir sus dos commits.

**Rama**: `fase-11/1-equipos-publicos`

## Decisiones de esta fase (no estaban en la propuesta, se toman aquí)

- **URL por id, no por slug**: `/equipos/12`. Un slug obligaría a una columna nueva, única y
  rellenada hacia atrás; la fase dejaría de ser aditiva por una cuestión de estética de URL.
  Queda anotado como posible mejora.
- **La pestaña va en la ruta, la temporada en la query**: `/equipos/12/jugadores?temporada=3`.
  Una pestaña es una página —se enlaza y se comparte—; la temporada es un filtro, y el resto
  del sitio ya la lleva en `?temporada`.
- **El selector ofrece sólo las temporadas que el club jugó**, y si la vigente no es una de
  ellas cae en la última que sí. Un club no inscrito este año tiene ficha igualmente: es lo
  que la identidad permanente de la Fase 9 existe para permitir.
- **Pestaña desconocida → General**, como `?jornada` inexistente cae en la jornada en curso.

## Unidad 1 — Listado de equipos (TDD)

- [x] 1.1 RED: `/equipos` lista los clubes de la temporada vigente agrupados por división,
      cambia con `?temporada`, y un club sin división no desaparece del listado.
- [x] 1.2 `Site\ClubsController@index` + vista, con el selector de temporada que ya usan
      Clasificación y Partidos.
- [x] 1.3 «Equipos» en la barra de navegación, junto a Noticias, y en el pie.

## Unidad 2 — La ficha y sus seis pestañas (TDD)

- [x] 2.1 RED: `/equipos/{club}` abre en General; cada pestaña responde en su ruta; una
      inventada cae en General; un club sin ficha en esa temporada no revienta.
- [x] 2.2 `Site\ClubsController@show` con la lista blanca de pestañas y el selector de
      temporada acotado a las del club.
- [x] 2.3 Cabecera de la ficha: escudo, nombre, año de fundación, estadio y división de la
      temporada elegida.

## Unidad 3 — General (TDD)

- [x] 3.1 RED: próximo partido, últimos cinco resultados como G/E/P, posición en la tabla,
      máximo goleador y máximo asistente del club, y el once ideal dibujado.
- [x] 3.2 `ClubSeasonService`: todo derivado de `games` y `game_events`, sin tabla nueva
      (design D12), como ya hacen `StandingsService` y `GoalscorersService`.
      **HALLAZGO**: ordenar los partidos por dos criterios a la vez los dejaba desordenados
      —el multiorden de `Collection` falla cuando el segundo criterio mezcla fechas con
      nulos, y la hora de un partido es opcional—. Se ordena por una clave compuesta.
- [x] 3.3 `GoalscorersService` gana un filtro por equipo en lugar de un servicio nuevo
      (design D12). La pertenencia de la temporada es la que dice si el gol es de este club.
- [x] 3.4 El dibujo del once se comparte con el panel: las líneas de una formación salen de
      `Lineup`, que es donde vive la formación, y no se copian en dos vistas.
      **HALLAZGO**: `Lineup` ya tenía un `rows()` que devolvía sólo el TAMAÑO de cada línea y
      no lo usaba nadie; se sustituye por el que devuelve los números de hueco.

## Unidad 4 — Partidos, Jugadores y Trofeos (TDD)

- [x] 4.1 RED: Partidos lista los del club en la temporada, jugados y por jugar, por jornada.
- [x] 4.2 RED: Jugadores muestra la plantilla de esa temporada —dorsal, posición específica y
      edad— agrupada por posición general, y marca las cesiones.
- [x] 4.3 RED: Trofeos muestra el palmarés ENTERO, no el de la temporada elegida: un título
      se gana una vez y se exhibe siempre (es para lo que existe la entidad Club).

## Unidad 5 — Stats y Técnico (TDD)

- [x] 5.1 RED: Stats da partidos jugados, ganados, empatados, perdidos, goles a favor y en
      contra, diferencia, puntos, tarjetas y porterías a cero de esa temporada.
- [x] 5.2 RED: Técnico muestra el nombre de la cuenta del técnico asignado (decisión cerrada)
      y dice que no hay ninguno cuando el club está sin técnico.

## Unidad 6 — Especificación y cierre

- [x] 6.1 Deltas en `public-views` con la sección nueva, y en `coach-panel` nada: esto es
      sitio público, no panel.
- [x] 6.2 Suite en verde (369) y Pint limpio sobre `app/`, `tests/` y `resources/`.
- [x] 6.3 Marcar las tareas y anotar el estado.
- [x] 6.4 Las siete rutas comprobadas contra el contenedor local con los datos del seed:
      listado y las seis pestañas responden 200 y pintan lo suyo.

## Lo que queda por hacer a mano

- [ ] V.1 Mirar la sección en el navegador con ojos, no con `curl`: es la primera parte
      pública que no sale del mockup de referencia.
- [ ] V.2 Desplegar. Sin migraciones, así que el despliegue es sólo código.
- [ ] V.3 Pendiente anotado, no defecto: la URL de un club lleva su id. Un slug pide columna,
      unicidad y relleno hacia atrás, y encaja mejor en una fase que ya toque el esquema.

---

# Tasks: Fase 12 — Contabilidad

**Estado: aplicada.** 408 tests en verde (línea base 369), Pint limpio sobre lo tocado. Tres
migraciones, todas aditivas.

**Rama**: `fase-12/1-contabilidad`

## Decisiones de esta fase (no estaban en la propuesta, se toman aquí)

- **Un traspaso dentro de la liga es UNA fila y se registra como fichaje del comprador**
  (design D8: *una fila leída desde los dos lados*). Registrarlo además como venta del
  vendedor sería la segunda fila que D8 descarta, y el dinero se contaría dos veces. La ficha
  del vendedor lo muestra como salida, que es cómo se lee desde su lado.
- **`venta` es, por tanto, el tipo de una salida FUERA de la liga**, y el modelo lo exige.
- **El saldo es acumulado, no por temporada**: el dinero de un club no se reinicia en agosto.
  Los movimientos llevan temporada y la lista se filtra por ella, que es lo que pedía la
  propuesta.
- **Estado `rechazado`, además de propuesto y aprobado.** D7 nombraba dos; el admin necesita
  decir que no, y la alternativa —borrar la propuesta— destruye el rastro que el libro de
  movimientos existe para conservar. El saldo sigue contando sólo los aprobados.
- **Un traspaso no se edita.** Se ejecuta al crearse, así que editarlo después movería
  plantillas y dinero por segunda vez o no los movería en absoluto. Borrarlo se lleva sus
  movimientos por la FK; la plantilla NO vuelve sola, y la confirmación lo dice.

## Unidad 1 — El valor de un jugador (TDD)

- [x] 1.1 RED: `players.market_value` existe, es opcional, y no admite negativos.
- [x] 1.2 Migración aditiva y campo en el formulario de identidad de `/admin`. Valor único,
      no por temporada (decisión cerrada).
- [x] 1.3 El técnico lo VE en su plantilla y no lo toca: lo fija el administrador.
- [x] 1.4 Ficha pública: valor por jugador y total de la plantilla de esa temporada. Entero
      con separador de miles y sin símbolo de moneda (decisión cerrada).

## Unidad 2 — El presupuesto como libro de movimientos (TDD)

- [x] 2.1 RED: el saldo es `clubs.initial_balance` más los ingresos aprobados menos los
      egresos aprobados; lo propuesto no cuenta.
- [x] 2.2 Migración: `clubs.initial_balance` y `budget_movements` (club, temporada, tipo,
      importe, razón, estado, quién lo creó).
- [x] 2.3 `BudgetService`: el saldo se deriva, no se guarda (design D7, como la clasificación).
      **HALLAZGO**: el `SELECT` del saldo tiene que traer `status` aunque el `WHERE` ya lo
      acote — una columna no seleccionada llega como null, que aquí significaba "no aprobado"
      y dejaba todos los saldos clavados en el inicial. Lo cazaron los tests.
- [x] 2.4 `/admin`: alta directa ya aprobada, y aprobar o rechazar lo que propone un técnico.
- [x] 2.5 `/club`: el técnico propone con su razón, ve su saldo y su libro, y no puede
      aprobar ni tocar lo aprobado.

## Unidad 3 — Traspasos (TDD)

- [x] 3.1 RED: un fichaje entre clubes de la liga genera DOS movimientos ya aprobados —egreso
      del comprador, ingreso del vendedor— y mueve al jugador de plantilla.
- [x] 3.2 RED: un préstamo mueve al jugador y NO genera dinero (decisión cerrada), y la
      pertenencia nueva es de tipo cesión, sin cambiar el club propietario.
- [x] 3.3 RED: una venta fuera de la liga genera el ingreso, saca al jugador de la plantilla
      y lo marca como salido conservando su ficha (design D9: sus goles y tarjetas cuelgan de
      él con borrado en cascada).
- [x] 3.4 Migración: `transfers`, `budget_movements.transfer_id` y `players.left_at`/`left_to`.
- [x] 3.5 `TransferService` + observador de `Transfer::created`, mudo bajo
      `WithoutModelEvents` como los guards que ya existen.
      **DECISIÓN**: el dorsal de la pertenencia nueva es el primero libre. La columna es
      obligatoria y única por equipo, y un traspaso no es el momento de elegir dorsal: se
      retoca desde la plantilla de la temporada, que es donde vive.
- [x] 3.6 `/admin`: registro con filtro por temporada, club y tipo. `/club`: sólo lectura.

## Unidad 4 — Especificación y cierre

- [x] 4.1 Deltas en `league-data-model` (las dos tablas nuevas y sus reglas), `admin-league-crud`
      y `coach-panel`; el valor de jugador, en `public-views`.
- [x] 4.2 Suite en verde (408) y Pint limpio sobre lo tocado.
- [x] 4.3 Marcar tareas y anotar estado.

## Lo que queda por hacer a mano

- [ ] V.1 Probar en el navegador el circuito entero con cuentas reales: el técnico propone, el
      administrador aprueba, y un fichaje entre dos clubes deja los dos saldos cuadrados.
- [ ] V.2 Fijar los saldos iniciales de los clubes de producción, que nacen en 0.
- [ ] V.3 Desplegar. Las tres migraciones son aditivas y el contenedor las aplica al arrancar.
- [ ] V.4 Pendiente anotado, no defecto: un préstamo no vuelve solo al vencer el plazo. Es
      decisión cerrada —no hay tareas programadas en este despliegue— y el regreso lo registra
      el administrador.

## Fases siguientes (esqueleto)

- **Fase 13** — Chat y ofertas.
