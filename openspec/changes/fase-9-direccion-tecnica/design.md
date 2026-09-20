# Design: Fases 9–13 — Dirección Técnica

Decisiones que atraviesan más de una fase, con su razón. Las que sólo afectan a una fase se
escribirán en el diseño de esa fase.

## D1 — `Club` es la identidad; `Team` pasa a ser su participación en una temporada

`clubs`: `name` (único), `short_name`, `crest_path`, `founded_year`. `teams` conserva
`season_id` y `division_id`, gana `club_id` y **cede** esos cuatro campos.

Alternativa descartada: agrupar filas de `teams` por un slug compartido. Habría evitado la
migración, pero nada impediría que dos filas del mismo club discreparan en nombre o escudo, y
el escudo es justamente lo que se pinta arriba a la derecha cuando el técnico entra.

`Team::name`, `short_name`, `crest_path` y `founded_year` se conservan como **accesores que
delegan en el club**. Hay decenas de puntos que leen `$team->name` — vistas públicas, tablas
del panel, servicios, factories y tests — y un accesor los deja funcionando sin tocarlos. Lo
que desaparece es escribir esos campos en `teams`.

La unicidad se mueve con ellos: `unique(season_id, name)` en `teams` pasa a ser `unique(name)`
en `clubs` más `unique(season_id, club_id)` en `teams` — un club no puede estar dos veces en
la misma temporada. Esa segunda restricción es también lo que sostiene el `down()` de la
migración (ver el plan de reversión de la propuesta).

## D1b — El jugador se parte en identidad (club) y pertenencia (temporada)

Hoy `players` mezcla dos cosas: **quién es** un jugador (nombre, fecha de nacimiento,
posición) y **dónde juega este año** (`team_id`, `shirt_number`, con `unique(team_id,
shirt_number)`). Esa mezcla es la razón de que una plantilla sea por temporada y de que haya
que teclearla entera cada año.

Se parten en dos:

- `players` conserva la identidad y gana `club_id`, el club **propietario**.
- `squad_memberships`: `team_id` (la participación del club en una temporada y división),
  `player_id`, `shirt_number` y `type` (`owned` o `loan`), con `unique(team_id, shirt_number)`
  y `unique(team_id, player_id)`.

Nótese que ese primer índice es literalmente el que `players` tiene hoy: la tabla nueva no
inventa una restricción, se lleva la que ya existía al sitio donde significa algo. Y resuelve
un defecto que estaba enterrado: **el dorsal cambia de temporada**, así que atarlo al club y
para siempre —lo que pasaría si el jugador colgara sólo del club— habría sido falso.

`stadiums.team_id` pasa a `stadiums.club_id`: un club no cambia de estadio al cambiar de año.

Ventaja que viene de regalo: `game_events.player_id` apunta ahora a una identidad que sobrevive
a las temporadas, así que los goles de un jugador se acumulan en su ficha en lugar de repartirse
entre una fila por año, que es lo que ocurre hoy sin que nadie lo haya pedido.

La cesión deja de ser un caso especial: es una pertenencia cuyo `type` es `loan` y cuyo club
propietario (`players.club_id`) no es el del `team_id` donde juega. La Fase 12 escribe eso, no
un mecanismo aparte.

**Qué plantilla se lee en cada sitio**: la del técnico y la ficha pública son las pertenencias
de la temporada seleccionada. La etiqueta de club en la lista de goleadores sale de la
pertenencia de esa temporada — que es donde el jugador realmente jugó, cesiones incluidas —, y
cae al club propietario si no hubiera ninguna.

## D1c — Inscribir un club en una temporada arrastra su plantilla

Crear la fila de `teams` que inscribe a un club en una temporada copia las pertenencias de su
participación anterior más reciente: mismos jugadores, mismos dorsales, y desde ahí se ajusta.
Decisión del propietario: automático al inscribir, no un botón aparte.

Se implementa como un observador de `Team::created`, y por tanto queda mudo bajo
`WithoutModelEvents` — que es lo que usa `DatabaseSeeder` y lo que ya hace con los guards de
`Game`, `Season` y `Matchday`. El seeder arma sus plantillas explícitamente, como hasta ahora.

Los jugadores marcados como salidos de la liga (D9) no se heredan.

## D2 — La migración crea un club por nombre distinto, y falla ruidosamente si algo no cuadra

`INSERT INTO clubs SELECT DISTINCT name ...` y después el `club_id` de cada fila de `teams`.
Si dos temporadas escribieron el mismo club con nombres distintos ("Manaos FC" y "Manaos
F.C."), quedarán como dos clubes: no hay forma automática de saber que son el mismo, y
adivinarlo sería peor que dejarlo visible para que el admin lo una a mano.

En producción hoy hay 12 equipos en una temporada y 0 en la otra, así que el caso ambiguo no
existe todavía. Es ahora o nunca.

## D3 — El rol vive en `users.role`, no en un `is_admin`

Ya se argumentó en el diseño D9 de la Fase 7: `canAccessPanel()` es una puerta, no un permiso.
Un `is_admin` booleano tendría que concederse igualmente al técnico para que entrara en su
panel, y el nombre de la columna sería mentira. `role` admite `admin` y `tecnico`, y el límite
real lo ponen las políticas por registro.

`users.club_id` es nulo para el admin y obligatorio para el técnico, con una invariante de
modelo que lo exige — misma forma que los guards de `Game`, `Season`, `Matchday` y
`StandingZone`.

El acceso sigue siendo por **correo**, también para los técnicos: el admin les asigna uno al
crear la cuenta. Se valoró un `username` porque el propietario pidió "usuario y contraseña",
y se descartó por su decisión — añadirlo obligaría a tocar la autenticación de Filament en
ambos paneles para no ganar nada funcional.

## D4 — Dos paneles Filament, no uno con el menú recortado

`/admin` sigue siendo del administrador. El técnico entra en `/club`, un panel propio con su
marca, su idioma y sólo sus recursos. Esconder recursos dentro de un mismo panel deja la ruta
viva: basta teclear la URL. Dos paneles hacen que el recurso **no exista** para quien no debe
verlo, y el registro de cada uno es una lista explícita en lugar de una negación que hay que
acertar.

Las políticas por registro se escriben de todos modos: son la segunda cerradura, la que
protege de un `/club/players/3/edit` de otro club.

## D5 — El once ideal y el chat son pantallas propias

Ambos son interfaces que un CRUD no cubre: un campo con jugadores colocados por formación, y
una conversación. Se construyen como componentes Livewire montados dentro del panel `/club`,
que da sesión, permisos y navegación sin tener que rehacerlos.

## D6 — El once ideal guarda posiciones, no coordenadas

`lineups`: `team_id`, `formation` (de la lista cerrada), y por cada hueco de la formación el
jugador asignado. Guardar píxeles ataría el dato al tamaño del campo dibujado hoy; guardar
"hueco 7 de 4-3-3" sobrevive a cualquier rediseño y permite pintarlo igual en el panel y en la
ficha pública.

El once pertenece al equipo-temporada, no al club: una alineación de 2025/26 no describe la
plantilla de 2026/27.

## D7 — El dinero es un libro de movimientos, no un saldo guardado

`budget_movements`: club, temporada, tipo (ingreso o egreso), importe, razón, estado
(propuesto o aprobado) y quién lo creó. El saldo se **deriva**: inicial más ingresos aprobados
menos egresos aprobados.

Es la misma decisión que ya rige la clasificación (`ARQUITECTURA.md` §3: *se deriva, no se
guarda como fuente de verdad*). Un saldo guardado se desincroniza en cuanto un movimiento se
edita o se rechaza, y nadie se entera.

## D8 — Un traspaso es un registro que genera sus movimientos

`transfers`: tipo (fichaje, venta, préstamo), ámbito (dentro o fuera de la liga), jugador,
club origen, club destino, club externo (texto, cuando una de las dos puntas está fuera),
importe, plazo del préstamo y temporada.

Dentro de la liga, guardar un traspaso crea **dos** movimientos de presupuesto ya aprobados
—egreso en el comprador, ingreso en el vendedor— con la razón y el importe puestos, y cambia
el `team_id` del jugador. Una sola escritura, dos presupuestos cuadrados. Los préstamos no
generan movimientos: no tienen coste (decisión del propietario).

Que la compra de un club sea la venta del otro no se modela como dos filas: es **una** fila
leída desde los dos lados. Dos filas es cómo se llega a que un club haya vendido un jugador
que nadie compró.

## D9 — Las salidas de la liga no borran al jugador

`players.left_at` y `players.left_to` (texto del club externo). Un jugador marcado como salido
desaparece de la plantilla y de la ficha pública, y conserva su fila. Borrarlo se llevaría por
delante sus `game_events` —la FK es `cascadeOnDelete`— y con ellos los goleadores históricos y
las tarjetas de partidos ya jugados, que cambiarían solos.

## D10 — La oferta es una máquina de estados dentro del chat

`offers`: conversación, jugador, importe, estado (enviada, aceptada, rechazada, en
negociación, ejecutada) y quién la movió. Aceptar **no** toca plantillas ni presupuestos:
marca la oferta como acordada y la hace aparecer en una bandeja del admin. El traspaso real
sigue siendo una escritura de D8, hecha por el admin.

Esto mantiene una sola puerta de entrada al dinero y a las plantillas. Si aceptar ejecutara,
habría dos caminos que escriben lo mismo y uno de ellos sin nadie mirando.

## D11 — El chat se actualiza por sondeo

Livewire con `wire:poll` de unos segundos. La alternativa, Reverb o Pusher, exige un proceso
permanente o un servicio externo; este despliegue escala a cero y no tiene ninguno de los dos.
Para una liga de doce clubes, el sondeo es suficiente y no añade infraestructura.

## D12 — Las stats por temporada se derivan de lo que ya existe

Goles a favor y en contra salen de `games` con ambos marcadores puestos; tarjetas y porterías
a cero, de `game_events`, que desde 2026-09-20 ya las registra. Partidos jugados, de los
mismos `games`. No se guarda ninguna tabla de estadísticas: mismo motivo que D7.

El máximo goleador y asistente del club reutilizan `GoalscorersService`, que hoy computa por
temporada; necesita un filtro por club, no un servicio nuevo.

## D13 — El idioma se resuelve por panel, no por aplicación

`/club` en español, `/admin` en inglés. Las etiquetas de Filament se fijan por recurso
(`->label()`), que es lo que ya hace el panel actual, así que no hace falta cargar traducciones
ni cambiar el locale de la aplicación — y el sitio público, que ya está en español, no se toca.

## D14 — Sin sesión, la web pública es exactamente la de hoy

Ni el enlace de acceso ni el escudo aparecen para un visitante anónimo, y ninguna vista pública
consulta datos nuevos por el hecho de existir el rol. La sección de Equipos (Fase 11) es
pública para todos, con y sin sesión: es parte del sitio, no del panel.
