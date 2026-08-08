# Exploration — Fase 2: Modelo de datos

## Current state

Fase 1 está cerrada y archivada. Solo existen las migraciones de stock
(`users`, `cache`, `jobs`). No hay tablas de dominio, modelos, factories ni
seeders todavía — `database/seeders/DatabaseSeeder.php::run()` está vacío
(lo dejamos así al remover el seed de admin durante la verificación de
Fase 1).

El modelo de dominio está fijado por `ARQUITECTURA.md` §4 (no se
renegocia, solo se implementa):

```
Season 1───* Team 1───* Player
              │  └──1 Stadium
              │
Season 1───* Matchday 1───* Game *───1 home Team
                                 *───1 away Team
```

`Standing` es explícitamente derivado (no hay tabla).

**Convención de modelos ya establecida en el código** (`app/Models/User.php`):
atributos PHP 8 para configuración — `#[Fillable([...])]` y `#[Hidden([...])]`
sobre la clase, no las propiedades clásicas `protected $fillable`. Los casts
usan el método `protected function casts(): array`, no la propiedad
`protected $casts`. Los modelos nuevos de Fase 2 deben seguir este mismo
estilo por consistencia.

**Test runner — realidad vs. `config.yaml`**: `openspec/config.yaml` dice
`planned_runner: Pest o PHPUnit`, pero `composer.json` solo trae
`phpunit/phpunit ^12.5.12` instalado (`pestphp/pest-plugin` está permitido
pero no instalado). Con `strict_tdd: true` activo, los tests de esta fase
deben ser PHPUnit (`tests/Feature/*Test.php`), no sintaxis Pest, salvo que
se decida explícitamente instalar Pest antes.

`config/filesystems.php` está sin modificar (stock): disco `public` →
`storage_path('app/public')`, con el symlink `public/storage` declarado en
`links` pero **nunca creado** (`php artisan storage:link` no corrió nunca
en este repo).

El stack Docker (sin cambios desde Fase 1): `app`/`web` comparten el mismo
bind mount (`./:/var/www/html` sobre una ruta NTFS de Windows vía
Docker Desktop/WSL2). Al ser bind mount en vivo, cualquier archivo que
escriba `app` bajo `storage/` —incluido un symlink— es visible para `web`
sin rebuild.

## Áreas afectadas

- `database/migrations/*_create_{seasons,teams,players,stadiums,matchdays,games}_table.php` (nuevas)
- `app/Models/{Season,Team,Player,Stadium,Matchday,Game}.php` (nuevos)
- `database/factories/{Season,Team,Player,Stadium,Matchday,Game}Factory.php` (nuevas)
- `database/seeders/DatabaseSeeder.php` (modificar — orden Season → Team → Stadium → Player → Matchday → Game, por FKs)
- `config/filesystems.php` — probablemente sin cambios, pero validar contra `FILESYSTEM_DISK` de `.env` en apply
- `docker/nginx/default.conf` — candidato a cambio si se elige el enfoque de alias nginx para servir `storage/app/public`
- `storage/app/public/crests/` (nueva) — debe ser escribible por `www-data`
- `tests/Feature/*Test.php` (nuevos, por `strict_tdd: true`)

## Approaches

### 1. Servir el disco público para `Team.crest_path` (el wiring de Filament es Fase 3, pero el plumbing de storage es de esta fase)

1. **`php artisan storage:link` estándar** — el camino vainilla de Laravel/Filament.
   - Pros: cero desvío de los defaults del framework; comando idempotente.
   - Contras: crear un symlink desde un contenedor Linux sobre un bind mount NTFS de Windows es un punto de falla documentado (Docker Desktop WSL2/`drvfs` puede rechazar la creación del symlink sin "Developer Mode" en el host, o crear un link que el contenedor ve válido pero no resuelve bien). Fase 1 ya marcó fricción NTFS como riesgo aceptado, pero nunca probó symlinks específicamente.
   - Esfuerzo: Bajo si funciona; impredecible si no.

2. **Alias de nginx, sin symlink** — agregar `location /storage/ { alias /var/www/html/storage/app/public/; }` a `docker/nginx/default.conf`. `Storage::disk('public')->put(...)` y `crest_path` en la DB no cambian, solo el mecanismo de *servir* el archivo.
   - Pros: evita por completo la clase de riesgo del symlink en Windows; determinístico; sin dependencia de Developer Mode en el host.
   - Contras: diverge de lo que asume un deploy Laravel vainilla — hay que documentarlo para que un deploy futuro no-Docker no rompa `/storage/...` en silencio.
   - Esfuerzo: Bajo.

**Recomendación**: probar `storage:link` primero (barato, estándar), pero comprometer el alias de nginx como fallback ya decidido —no descubierto a mitad del apply—, dado el riesgo NTFS ya confirmado para este setup.

### 2. Comportamiento de borrado en cascada para `Game.home_team_id` / `Game.away_team_id`

1. **Cascade en todo** (incluyendo ambos FKs de equipo en `Game`).
   - Contras: borrar un Team borraría en silencio todos los Games donde ese equipo participa — incluyendo el lado del rival, que nadie pidió borrar. Pérdida de datos cruzada silenciosa.

2. **Cascade en relaciones de dueño exclusivo; `restrictOnDelete()` en ambos FKs de equipo de `Game`.**
   - `Team→Season`, `Player→Team`, `Stadium→Team`, `Matchday→Season`, `Game→Matchday`: cascade.
   - `Game.home_team_id` / `Game.away_team_id`: `restrictOnDelete()`.
   - Pros: evita corrupción cruzada de datos; fuerza limpieza explícita antes de borrar un Team — protege el historial de partidos.
   - Contras: los seeders/reseteos de demo deben borrar en orden de dependencia (Games antes que Teams); Fase 3 necesita un mensaje de error amigable en `TeamResource` cuando el borrado se bloquee (nota para Fase 3, no bloqueante acá).

**Recomendación**: Approach 2.

## Columnas investigadas (punto de partida validado)

| Tabla | Columnas (además de `id`, `timestamps()`) | Restricciones |
|---|---|---|
| `seasons` | `name` (string), `start_date`, `end_date` (date) | `name` único |
| `teams` | `season_id` (FK→seasons, cascade), `name`, `short_name`, `crest_path` (string, nullable), `founded_year` (smallint sin signo, nullable) | único compuesto `(season_id, name)` — no global, porque el mismo nombre de club reaparece legítimamente entre temporadas (cada Team es una fila por temporada, no una identidad de club compartida, según el modelo fijado) |
| `players` | `team_id` (FK→teams, cascade), `name`, `position` (string, sin tabla enum), `birth_date` (date, nullable), `shirt_number` (tinyint sin signo) | único compuesto `(team_id, shirt_number)` |
| `stadiums` | `team_id` (FK→teams, cascade, **único** — fuerza 1 a 1), `name`, `city`, `capacity` (integer sin signo, nullable) | único en `team_id` |
| `matchdays` | `season_id` (FK→seasons, cascade), `number` (smallint sin signo), `date` (date, nullable — solo nominal/etiqueta) | único compuesto `(season_id, number)` |
| `games` | `matchday_id` (FK→matchdays, cascade), `home_team_id`/`away_team_id` (FK→teams, **restrict**), `kickoff_at` (datetime, nullable — hora autoritativa del partido), `home_score`/`away_score` (integer sin signo, nullable, según decisión confirmada) | índices en `matchday_id`, `home_team_id`, `away_team_id`; `home_team_id <> away_team_id` debería forzarse — ver Riesgos (no hay helper nativo `check()` en Blueprint de Laravel 13.x) |

## Recomendación

Implementar las 6 migraciones/modelos/factories como arriba, con: (a)
configuración de modelo basada en atributos (`#[Fillable]`, `casts()`)
igual que `User`, (b) `restrictOnDelete()` en ambos FKs de equipo de
`Game` y `cascadeOnDelete()` en el resto, (c) el alias de nginx como
fallback ya decidido junto a un intento de `storage:link`, (d) tests
PHPUnit (no Pest) por `strict_tdd: true`, y (e) seeders con un arreglo
curado de nombres futboleros en vez de Faker puro (FakerPHP no tiene
provider de fútbol, y nombres genéricos de empresa/persona quedarían mal
en un demo de liga).

## Riesgos

- **Riesgo de symlink NTFS en Windows para `storage:link`**: confirmado
  por búsqueda como una clase de falla real y documentada para bind
  mounts de Docker Desktop/WSL2 sobre rutas Windows (`drvfs`) — necesita
  una decisión explícita en design (alias nginx vs. symlink), no prueba y
  error en el apply.
- **`.env`/`.env.example` inaccesibles** para las herramientas del agente
  (mismo guardrail que Fase 1) — no se pudo confirmar el valor actual de
  `FILESYSTEM_DISK`. Riesgo bajo para esta fase (el wiring de Filament es
  Fase 3), pero conviene validar el plumbing de storage de punta a punta
  ahora.
- **No hay helper nativo `check()`** en las migraciones de Laravel 13.x
  para `home_team_id <> away_team_id` — necesita `DB::statement()` crudo o
  validación a nivel de modelo/form request; decidir explícitamente.
- **Unicidad de `shirt_number`**: las factories deben asignar números
  distintos por equipo deliberadamente (un `fake()->numberBetween()` plano
  colisiona) — necesita secuencia o pool explícito en la factory.
- **Desajuste de test runner**: `config.yaml` dice "Pest o PHPUnit" pero
  solo PHPUnit está instalado — hay que fijarlo explícitamente antes de
  que `strict_tdd` empiece a aplicar en el apply.
- **UX de `restrictOnDelete()`**: bloquea el borrado de un Team una vez
  que hay Games que lo referencian — correcto para integridad, pero
  Fase 3 necesita superficie de error amigable en `TeamResource` (nota
  para Fase 3, no bloqueante acá).
- **`Matchday.date` vs `Game.kickoff_at`**: dos campos temporales
  similares en niveles distintos — documentar claramente que
  `Matchday.date` es solo nominal/etiqueta y `Game.kickoff_at` es la hora
  autoritativa, para que no se conviertan en dos fuentes de verdad.

## Listo para propuesta

Sí. Dos decisiones deben quedar explícitas en `sdd-propose`/`sdd-design`
en vez de implícitas: (1) mecanismo de servir el disco público para
`Team.crest_path` (symlink vs. alias nginx vs. ambos), y (2) el mecanismo
de constraint CHECK para `home_team_id <> away_team_id` (SQL crudo vs.
solo validación a nivel de app).
