# Proposal: Fase 15 — Copas

**Roadmap phase**: Fase 15 (nueva). Continúa después del bloque 9–13 (Dirección Técnica) y
de la Fase 14 (propuestas de fichaje), ya en producción.

## Intent

Hoy la aplicación sólo sabe de ligas: se juegan todos contra todos, se deriva una tabla y se
acabó. El propietario quiere además **copas**: competiciones a eliminatoria, estilo Champions,
donde se cruzan equipos de divisiones distintas y quien pierde se va a casa.

## El hallazgo que ordena la fase

**Hoy una competición ES una división.** `divisions` cuelga de la temporada, `teams.division_id`
ata cada equipo a una sola, `matchdays` numera las jornadas de esa división y la clasificación
se deriva de sus partidos. Una copa rompe las tres cosas a la vez:

| Lo que una copa necesita | Por qué no encaja hoy |
|---|---|
| Equipos de varias divisiones | Un equipo pertenece a UNA división |
| Rondas, no jornadas numeradas | `matchdays` es (temporada, división, número) |
| Cuadro, no tabla | La clasificación es lo único que se deriva de los partidos |
| Un partido puede ser de ida o de vuelta | Un partido es de una jornada y punto |

Por eso la copa entra como una entidad propia al lado de la división, y no estirando la
división hasta que sirva para las dos cosas. Lo único que se toca de lo viejo es `games`:
un partido pasa a pertenecer **a una jornada de liga, a un cruce de copa o a un grupo de
copa**, y exactamente a uno de los tres.

## Decisiones cerradas con el propietario

1. **El formato lo elige el administrador por copa**: unas son sólo cuadro, otras llevan fase
   de grupos antes.
2. **El número de partidos se elige por ronda**: una final a partido único con semifinales a
   ida y vuelta es lo normal, y el modelo tiene que admitirlo.
3. **Si una eliminatoria acaba empatada, el administrador dice quién pasa y por qué.** No se
   modelan penaltis ni valor doble de los goles fuera: se guarda la decisión y su motivo, que
   es lo que de verdad hace falta consultar después.
4. **El cuadro lo arma el administrador ronda a ronda.** La aplicación le dice quién ganó cada
   cruce; emparejar es suyo, porque un sorteo automático es rígido justo el día que hay que
   corregir algo a mano.
5. **Las estadísticas se separan por competición**: goleadores, tarjetas y porterías a cero se
   pueden mirar de liga, de copa o en total.

## Reparto en unidades

1. **El modelo**: `cups`, participantes, grupos, rondas y cruces; `games` aprende a pertenecer
   a uno de los tres sitios.
2. **Lo que se deriva**: el resultado de una eliminatoria (global, ganador, si hace falta
   decisión) y la tabla de un grupo, reutilizando `StandingsService`.
3. **El panel del administrador**: crear la copa, apuntar equipos, armar rondas y cruces,
   cargar resultados y resolver un empate con su motivo.
4. **La parte pública**: la página de la copa con su cuadro y sus grupos, y los partidos de
   copa donde ya se miran los partidos.
5. **Las estadísticas por competición**, en las tres pantallas que las enseñan.

## Fuera de alcance

- Sorteo automático del cuadro (decisión 4).
- Penaltis, prórroga y valor doble de los goles fuera como datos: la decisión y su motivo
  bastan (decisión 3).
- Terceros y cuartos puestos, repescas y grupos con ida y vuelta desigual: si hacen falta,
  entran como rondas más.

## Plan de reversión

Puramente aditiva salvo un detalle: `games.matchday_id` pasa a admitir nulos. Revertirla es
revertir los commits, borrar las tablas nuevas y volver a poner la columna como obligatoria,
lo cual sólo es correcto mientras no haya partidos de copa cargados — y por eso la migración
de vuelta los borra explícitamente en vez de fallar a medias.
