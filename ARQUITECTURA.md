# Liga Amazon (Amazon Superleague) — Fundamentos del Proyecto

> Documento base de arquitectura, estructura y metodología de trabajo.
> Última actualización: 2026-08-07

---

## 1. Visión del proyecto

**Liga Amazon** es una aplicación web para gestionar y mostrar una liga de fútbol
al estilo de la Premier League: equipos, jugadores, estadios, jornadas, partidos,
resultados y tabla de posiciones calculada automáticamente. Incluye un panel de
administración para cargar y mantener toda la información, y una parte pública
donde los aficionados consultan calendario, resultados y clasificación.

El alcance inicial es un **demo funcional**: priorizamos un modelo de datos sólido,
un panel de administración completo con Filament y una vista pública básica.

---

## 2. Stack tecnológico

| Capa | Tecnología | Versión objetivo |
|------|------------|------------------|
| Lenguaje | PHP | 8.3+ (recomendado 8.4) |
| Framework | Laravel | 13.x |
| Panel admin | Filament | 5.x |
| Reactividad admin | Livewire | 4.x (incluido en Filament v5) |
| Base de datos | MySQL | 8.0 |
| Frontend público | Blade + Tailwind CSS | Tailwind 3.x |
| Build assets | Vite | incluido en Laravel |
| Servidor web | Nginx | estable |
| Ejecución PHP | PHP-FPM | 8.3+ |
| Contenedores | Docker + docker-compose | — |
| Control de versiones | Git + GitHub Flow | — |

> Nota: Laravel 13 exige PHP 8.3 como mínimo. Filament v5 corre sobre Livewire v4.

---

## 3. Arquitectura de la aplicación

Seguimos la arquitectura estándar de Laravel (MVC) reforzada con una capa de
servicios para la lógica de negocio que no pertenece ni al modelo ni al controlador
(por ejemplo, el cálculo de la tabla de posiciones).

```
Navegador / Cliente
        │
        ▼
   Nginx (reverse proxy, sirve estáticos)
        │
        ▼
   PHP-FPM  ──►  Laravel
                  ├─ Rutas (web.php)            → parte pública (Blade)
                  ├─ Filament Panel (/admin)    → administración
                  ├─ Controllers                → orquestan peticiones
                  ├─ Services                   → lógica de negocio (StandingsService, etc.)
                  ├─ Models (Eloquent)          → acceso a datos
                  └─ Migrations / Seeders       → esquema y datos de prueba
                  │
                  ▼
             MySQL 8.0 (datos persistentes en volumen Docker)
```

**Principios:**

- **Delgadez de controladores:** los controladores orquestan; la lógica vive en
  Services y Models.
- **Filament como back-office:** todo el CRUD administrativo se genera con Recursos
  de Filament, no con controladores manuales.
- **Cálculos derivados en Services:** la clasificación se calcula a partir de los
  resultados; no se guarda como fuente de verdad, se deriva.
- **Migraciones como fuente de verdad del esquema:** nunca se toca la BD a mano.

---

## 4. Modelo de datos (dominio)

Entidades principales para una liga de fútbol:

| Entidad | Descripción | Relaciones clave |
|---------|-------------|------------------|
| `Season` (Temporada) | Edición de la liga (p. ej. 2026/27) | tiene muchos partidos y equipos |
| `Team` (Equipo) | Club participante | pertenece a temporada; tiene jugadores; juega partidos |
| `Player` (Jugador) | Futbolista de un equipo | pertenece a un equipo |
| `Stadium` (Estadio) | Sede de un equipo | pertenece a un equipo |
| `Matchday` (Jornada) | Fecha/ronda de la liga | tiene muchos partidos |
| `Game` (Partido) | Encuentro local vs visitante | pertenece a jornada; local y visitante (Team); tiene resultado |
| `Standing` (Posición) | Fila de la tabla — **derivada** | calculada por temporada a partir de los partidos |
| `User` (Usuario) | Acceso al panel admin | roles/permisos |

**Notas de diseño:**

- `Game` referencia dos veces a `Team` (`home_team_id`, `away_team_id`).
- La tabla de posiciones se **calcula** (partidos jugados, ganados, empatados,
  perdidos, goles a favor/en contra, diferencia y puntos: 3 por victoria, 1 empate).
- Se puede añadir más adelante: tarjetas, goleadores, sanciones, árbitros.

**Esquema conceptual:**

```
Season 1───* Team 1───* Player
              │  └──1 Stadium
              │
Season 1───* Matchday 1───* Game *───1 home Team
                                 *───1 away Team
```

---

## 5. Estructura de directorios

Estructura estándar de Laravel + Filament (se genera al instalar; aquí queda
documentada la convención del proyecto):

```
Liga Amazon/
├── app/
│   ├── Filament/
│   │   └── Resources/        # Recursos del panel: TeamResource, GameResource, ...
│   ├── Http/
│   │   └── Controllers/      # Controladores de la parte pública
│   ├── Models/               # Season, Team, Player, Stadium, Matchday, Game, ...
│   ├── Services/             # StandingsService (cálculo de la tabla), etc.
│   └── Providers/
├── database/
│   ├── migrations/           # Esquema (fuente de verdad)
│   ├── seeders/              # Datos demo (equipos, jugadores, partidos)
│   └── factories/            # Generación de datos de prueba
├── resources/
│   ├── views/                # Blade (parte pública: tabla, calendario, resultados)
│   ├── css/
│   └── js/
├── routes/
│   └── web.php               # Rutas públicas
├── public/                   # Punto de entrada (index.php) y assets compilados
├── docker/                   # Config de contenedores (nginx, php)
│   ├── nginx/
│   │   └── default.conf
│   └── php/
│       └── Dockerfile
├── docker-compose.yml
├── .env                      # Config de entorno (NO se versiona)
├── .env.example              # Plantilla de variables
├── .gitignore
├── README.md
└── ARQUITECTURA.md           # este documento
```

---

## 6. Entorno Docker (docker-compose propio)

Tres servicios en desarrollo:

| Servicio | Imagen base | Rol |
|----------|-------------|-----|
| `app` | PHP 8.3-fpm (Dockerfile propio en `docker/php`) | Ejecuta Laravel (PHP-FPM) |
| `web` | nginx:alpine | Reverse proxy, sirve la app en `localhost:8080` |
| `db` | mysql:8.0 | Base de datos, datos en volumen persistente |

**Bosquejo de `docker-compose.yml`:**

```yaml
services:
  app:
    build: ./docker/php
    volumes:
      - ./:/var/www/html
    depends_on:
      - db

  web:
    image: nginx:alpine
    ports:
      - "8080:80"
    volumes:
      - ./:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app

  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: liga_amazon
      MYSQL_USER: liga
      MYSQL_PASSWORD: secret
      MYSQL_ROOT_PASSWORD: secret
    ports:
      - "3306:3306"
    volumes:
      - db_data:/var/lib/mysql

volumes:
  db_data:
```

> Los valores de contraseña de arriba son solo de ejemplo para desarrollo local;
> en el `.env` real se definen de forma segura y ese archivo no se versiona.

**Flujo de arranque (una vez creado el proyecto):**

1. `docker compose up -d --build`
2. `docker compose exec app composer install`
3. `docker compose exec app php artisan key:generate`
4. `docker compose exec app php artisan migrate --seed`
5. Abrir `http://localhost:8080` (público) y `http://localhost:8080/admin` (Filament)

---

## 7. Metodología de trabajo (Git + GitHub Flow)

**Ramas:**

- `main`: siempre desplegable y estable.
- `feature/<descripcion>`: una rama por funcionalidad (p. ej. `feature/standings-calculation`).
- Se abre Pull Request de la feature hacia `main`; se revisa y se fusiona.

**Commits convencionales** (Conventional Commits):

```
feat: agrega recurso de equipos en Filament
fix: corrige cálculo de diferencia de goles
chore: configura docker-compose con MySQL
docs: actualiza README con pasos de instalación
refactor: extrae lógica de tabla a StandingsService
```

Prefijos: `feat`, `fix`, `docs`, `chore`, `refactor`, `test`, `style`.

**Convenciones de código:**

- PSR-12 para PHP (formateo con Laravel Pint).
- Nombres de modelos en inglés y singular (`Team`, `Game`).
- Migraciones descriptivas y ordenadas por fecha.
- Nada de credenciales en el repositorio: todo en `.env` (ignorado por Git).

---

## 8. Fases del proyecto (roadmap)

1. **Fase 0 — Fundamentos (este documento).** Definir arquitectura, stack y metodología. ✅
2. **Fase 1 — Andamiaje.** Instalar Laravel 13 + Filament v5, levantar Docker, conectar MySQL.
3. **Fase 2 — Modelo de datos.** Migraciones, modelos y relaciones; seeders con datos demo.
4. **Fase 3 — Panel admin.** Recursos de Filament para equipos, jugadores, jornadas y partidos.
5. **Fase 4 — Lógica de liga.** `StandingsService`: cálculo de la tabla de posiciones.
6. **Fase 5 — Parte pública.** Vistas Blade: clasificación, calendario y resultados.
7. **Fase 6 — Pulido.** Diseño (integrar el frontend de Amazon Superleague), tests y ajustes.

---

## 9. Próximo paso

Con estos fundamentos aprobados, el siguiente paso (Fase 1) es generar el andamiaje:
Dockerfile de PHP, `docker-compose.yml`, configuración de Nginx e instalación de
Laravel + Filament. Avísame cuando quieras arrancar la Fase 1.
