# Proposal: Fases 9–13 — Dirección Técnica

**Roadmap phase**: Fases 9 a 13 (nuevas). Continúan después de la Fase 8 (despliegue en
Vercel) y de los retoques de 2026-09-20 (jornadas por división, filtros públicos, tarjetas
y porterías a cero, zonas de clasificación).

**Alcance de este documento**: el bloque entero, por decisión del propietario
(*"propuesta completa primero"*). Cada fase recibirá sus propias tareas y sus deltas de
especificación cuando le toque; lo que se fija aquí es el reparto, el modelo de datos y las
decisiones que atraviesan varias fases a la vez.

## Intent

Hoy la aplicación tiene un único tipo de usuario: el administrador, que lo puede todo desde
`/admin`, y un sitio público de sólo lectura. El propietario quiere abrir un segundo rol —el
**director técnico**— que entra desde la web pública, se ocupa de un club y dispone de cinco
módulos propios: plantilla, trofeos, sus enfrentamientos, contabilidad y un chat con ofertas
por jugador. En paralelo, la parte pública gana una sección de **Equipos** con la ficha de
cada club.

Esto no es una función más: es el disparador del pendiente **4.1 de `DESPLIEGUE.md`**, que ya
estaba anotado con máxima prioridad y esta condición exacta — *"Disparador: la llegada del rol
técnico"*. Hoy cualquier usuario del panel puede editar cualquier fila; con un único operador
es inofensivo, y deja de serlo en cuanto entre alguien que sólo debe tocar su club.

## El hallazgo que ordena todo el bloque

**Un club no existe entre temporadas.** `teams` cuelga de `season_id`, y
`openspec/specs/league-data-model/spec.md` lo dice sin ambigüedad:

> `teams` are per-season — the system MUST NOT model a cross-season club identity.

Casi todo lo pedido asume justo lo contrario:

| Lo pedido | Por qué necesita identidad permanente |
|---|---|
| Trofeos del club | Se ganan en una temporada y se exhiben en todas |
| El técnico se encarga de un club | Si no, habría que reasignarlo cada temporada |
| Historial de fichajes filtrable por temporada | Filtrar por temporada sólo tiene sentido si el club sobrevive a ellas |
| Ficha pública de un equipo | Es la del club, con su historia, no la de un año suelto |

Por eso la **Fase 9 introduce la entidad `Club`** y `teams` pasa a ser la participación de un
club en una temporada y división. Es la única de las cinco fases que toca lo ya construido, y
va primera a propósito: hacerla después obligaría a rehacer trofeos, fichajes y la ficha
pública.

## Reparto en fases

### Fase 9 — Entidad Club y rol Director Técnico

- Tabla `clubs` con la identidad permanente (nombre, nombre corto, escudo, año de fundación).
  `teams` conserva temporada y división, gana `club_id` y cede esos cuatro campos al club.
- Migración de datos: un club por cada nombre distinto de `teams`, con `club_id` rellenado.
- `users.role` (`admin` | `tecnico`), `users.club_id` para el técnico, y políticas por
  registro: un técnico sólo escribe sobre su club.
- Acceso desde el sitio público: enlace de entrada, sesión, y el escudo del club arriba a la
  derecha que lleva al panel del técnico. Sin sesión, la web queda **exactamente** como hoy.
- El admin crea las cuentas de técnico (usuario, contraseña y club) desde `/admin`.

### Fase 10 — Plantilla, Trofeos y Mis Enfrentamientos

- Posición específica por jugador, de una lista cerrada de 27 valores (DI, DC, DD, EI, SDI,
  SD, SDD, ED, MOI, MCO, MOD, MI, MD, MCI, MCID, MC, MDI, MDD, MCD, CAD, CAI, LI, LD, DCI,
  DCD, DFC, POR), editable por el técnico y por el admin.
- El técnico puede cambiar también la posición general, entre las cuatro existentes.
- **Once ideal**: formación más colocación de jugadores, en una pantalla propia estilo Fotmob.
- Trofeos: los crea el admin (nombre y temporada, colgando del club), el técnico los ve.
- Mis Enfrentamientos: los partidos de su club, filtrables por temporada y jornada.

### Fase 11 — Equipos en la parte pública

Sección nueva junto a Noticias: listado por divisiones y ficha de club con selector de
temporada y seis pestañas — General (predeterminada), Partidos, Jugadores, Trofeos, Stats y
Técnico. General muestra próximo partido, últimos cinco resultados, posición en la tabla, el
once ideal, y máximo goleador y asistente del club en la temporada.

### Fase 12 — Contabilidad

- Valor por jugador (lo fija el admin), visible en plantilla y en la ficha pública, con el
  total de la plantilla calculado.
- Presupuesto por club: saldo inicial fijado por el admin, más ingresos y egresos con su
  razón. **El técnico propone, el admin aprueba**; lo pendiente no cuenta en el saldo.
- Fichajes, ventas y préstamos, dentro y fuera de la liga, guardados siempre con la temporada
  vigente y filtrables por ella.

### Fase 13 — Chat y ofertas

Mensajería de texto uno a uno entre técnicos, y entre técnico y administradores (denominados
*presidentes* en el chat). Dentro de una conversación, **oferta por jugador**: se elige un
jugador de la plantilla del interlocutor y se envía un importe; el receptor acepta, rechaza o
negocia. Una oferta aceptada queda como acordada y le aparece al admin pendiente de ejecutar.

## Decisiones ya cerradas con el propietario

1. **Panel mixto**: plantilla, trofeos, enfrentamientos y contabilidad van en un panel
   Filament propio del técnico; el once ideal y el chat son pantallas propias en Livewire,
   porque son interfaces que Filament no hace bien.
2. **Presupuesto**: el técnico propone el movimiento con su razón, el admin lo aprueba.
3. **Dinero automático**: un fichaje, venta o préstamo entre clubes de la liga genera por sí
   mismo el egreso en el comprador y el ingreso en el vendedor, y mueve al jugador.
4. **Ofertas**: aceptar no ejecuta nada; avisa al admin, que firma.
5. **Identidad**: se crea la entidad `Club`.
6. **Salidas de la liga**: el jugador se marca como salido y su ficha se conserva, para no
   arrastrar sus goles y tarjetas de partidos ya jugados (los eventos cuelgan de él con
   borrado en cascada).
7. **Préstamos**: se anota el plazo (6 meses o 1 año) como dato; el regreso lo hace el admin.
8. **Un técnico por club, un club por técnico.**
9. **Panel del técnico en español**; `/admin` se queda en inglés.
10. **Valor de jugador único**, no por temporada.
11. Formaciones: 4-4-2, 4-3-3, 4-2-3-1, 4-1-4-1, 4-5-1, 3-5-2, 3-4-3, 5-3-2. Valores como
    entero con separador de miles, sin símbolo de moneda. Chat por sondeo, sin adjuntos, con
    contador de no leídos. El presupuesto no es público y un técnico sólo ve el suyo. Club de
    origen en un fichaje externo: texto libre. Ficha pública con selector de temporada.

## Fuera de alcance

- Fotos de jugadores: el once ideal muestra nombre y dorsal, como pidió el propietario.
- Notificaciones por correo. El aviso de oferta vive dentro del chat.
- Websockets. El chat se actualiza por sondeo; Reverb exigiría un proceso permanente que este
  despliegue no tiene.
- Tareas programadas. Por eso los préstamos no vencen solos (decisión 7).
- Traducir `/admin` al español.

## Plan de reversión

Cada fase es un despliegue propio y todas menos la 9 son puramente aditivas: revertir sus
commits y borrar sus tablas devuelve la aplicación al estado anterior.

La Fase 9 es la única con riesgo real, porque mueve columnas de `teams` a `clubs`. Su
migración `down()` debe devolver los cuatro campos a `teams` y rellenarlos desde el club,
y eso sólo es correcto mientras un club tenga una única fila de `teams` por temporada — que
es el caso hoy y lo seguirá siendo. La migración se prueba en local **y** contra un TiDB
desechable antes de tocar producción, como ya se hizo con `division_id`.

Recordatorio operativo: desde 2026-09-20 las migraciones se aplican solas al arrancar el
contenedor, así que revertir el código **no** revierte el esquema. El rollback manual está en
`DESPLIEGUE.md` §5.1.

## Preguntas abiertas

- **O1 — Identificador de acceso del técnico.** El propietario pidió "usuario y contraseña",
  pero Laravel y Filament autentican hoy por `email`. La propuesta es añadir `users.username`
  único y que el panel del técnico entre por usuario, dejando `/admin` con email. Falta
  confirmarlo.
- **O2 — Nombre visible del técnico** en la ficha pública: ¿el `name` de su cuenta, o un campo
  aparte? La propuesta es reutilizar `name`.
- **O3 — Jugadores y temporadas.** `players` cuelga de `teams`, así que hoy una plantilla
  también es por temporada. Al crear una temporada nueva habrá que decidir si las plantillas
  se copian, se heredan o se rehacen a mano. No bloquea las fases 9 a 13, pero llegará.
