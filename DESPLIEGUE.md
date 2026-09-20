# Despliegue — Liga Amazon

Estado: **el destino de hosting aún no está decidido.** Este documento separa
deliberadamente lo que ya está resuelto en el código de lo que no puede
resolverse hasta elegir servidor. Nada de lo pendiente se ha perdido: si está
en §3 es porque depende del hosting, no porque se haya olvidado.

---

## 1. Qué protege ya la aplicación

Implementado y cubierto por tests automáticos (Fase 7):

| Control | Dónde | Test |
|---|---|---|
| El panel funciona con `APP_ENV=production` | `User::canAccessPanel()` | `PanelAccessTest` |
| No existe ninguna vía de alta de usuarios fuera del CLI | panel sin registro, sin ruta `register` | `PanelAccessTest` |
| Subidas limitadas a jpeg/png/webp/gif, máx. 2 MB — **SVG rechazado** | `TeamForm`, `NewsForm` | `UploadValidationTest` |
| Cabeceras de seguridad en todas las respuestas, panel incluido | `SecurityHeaders` (stack global) | `SecurityHeadersTest` |
| HSTS sólo sobre HTTPS, sin `preload` | `SecurityHeaders` | `SecurityHeadersTest` |
| Rutas públicas limitadas a 60 req/min por IP | `routes/web.php` | `SecurityHeadersTest` |
| `APP_FORCE_HTTPS` como interruptor propio, apagado por defecto | `config/app.php` | `SecurityHeadersTest` |
| Plantilla de producción segura por construcción | `.env.production.example` | `ProductionEnvTemplateTest` |
| `/admin` excluido de indexación | `public/robots.txt` | — |

Heredado del framework, verificado en su código fuente durante la auditoría:
login con límite de 5 intentos (Filament), CSRF, cookies cifradas, invalidación
de sesión al cambiar contraseña, contraseñas con bcrypt coste 12, consultas
parametrizadas vía Eloquent, escapado automático de Blade, asignación masiva
cerrada con `#[Fillable]` en los 10 modelos.

---

## 1 bis. CORS del bucket de objetos

En un host sin disco propio, las subidas **temporales** de Livewire van tambien
al bucket (`LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=s3`). El motivo no es el disco
efimero sino que hay varias instancias: Livewire escribe el temporal en una
peticion y lo lee en la siguiente, que puede caer en otra instancia. Con disco
local el sintoma es

```
Unable to retrieve the file_size for file at location: livewire-tmp/....jpeg
```

Con disco s3 Livewire firma una URL y **el navegador sube directo al bucket**.
Eso es una peticion entre origenes, asi que el bucket necesita esta regla CORS,
o el navegador la bloquea antes de enviarla — y entonces no queda ni rastro en
los logs del servidor, porque el servidor nunca se entera.

```json
[
  {
    "AllowedOrigins": ["https://liga-amazon.vercel.app"],
    "AllowedMethods": ["PUT", "GET", "HEAD"],
    "AllowedHeaders": ["*"],
    "ExposeHeaders": ["ETag"],
    "MaxAgeSeconds": 3600
  }
]
```

`AllowedOrigins` debe coincidir con `APP_URL`. Al cambiar de dominio hay que
actualizarla, o las subidas dejan de funcionar sin error visible en servidor.

Se aplica con `./scripts/configure-r2-cors.sh`, que **necesita un token de R2
con Admin Read & Write**: el de `Object Read & Write` sube y borra ficheros pero
no puede tocar la configuracion del bucket, y devuelve 403. La alternativa es
pegarla a mano en el panel de Cloudflare: R2 -> el bucket -> Settings -> CORS
Policy.

---

## 2. Pasos de despliegue

En orden. Los pasos marcados **⚠** dependen de decisiones de §3.

1. Copiar `.env.production.example` a `.env` **en el servidor**. Nunca subir el
   `.env` de desarrollo.
2. Rellenar `APP_URL`, `DB_USERNAME`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`.
3. `php artisan key:generate` — **clave nueva**, jamás la de desarrollo.
4. `composer install --no-dev --optimize-autoloader`
5. `npm ci && npm run build`
6. `php artisan migrate --force` — **sin `--seed`**. Los datos se cargan desde
   el panel. En el contenedor de Vercel este paso **ya no es manual**: lo hace
   el entrypoint en cada arranque (§5.1).
7. `php artisan storage:link` — obligatorio. El symlink del repo apunta a
   `/var/www/html/...` (ruta absoluta del contenedor) y queda roto en cualquier
   otro sitio. Sin esto no se ve ningún escudo ni portada.
8. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
9. `php artisan make:filament-user` — interactivo. Ningún seeder crea usuarios.
10. **⚠** Verificar que el certificado TLS funciona **antes** de que llegue
    tráfico: `APP_FORCE_HTTPS=true` y `SESSION_SECURE_COOKIE=true` asumen HTTPS
    operativo, y la cabecera HSTS se emitirá en cuanto la primera petición
    llegue por HTTPS.
11. Comprobar permisos de escritura de `storage/` y `bootstrap/cache/` para el
    usuario del servidor web.

### Antes de cada despliegue

```bash
composer audit      # debe reportar cero advisories
npm audit           # ver nota en §3.9
php artisan test    # suite completa en verde
```

---

## 3. Pendiente — falta elegir hosting

Cada punto tiene una respuesta distinta según el servidor. Fijar una ahora
sería fijar la equivocada.

**3.1 Terminación TLS y certificado.** Quién termina HTTPS: ¿Caddy, nginx con
Certbot, un balanceador gestionado, Cloudflare? Determina 3.2 y 3.3.

**3.2 `->trustProxies()` en `bootstrap/app.php`. ⚠ Más crítico de lo que
parece — tiene DOS consecuencias, no una.**

*(a) URLs.* Es la pareja de `APP_FORCE_HTTPS`. Sin él, detrás de un proxy que
termina TLS, Laravel no ve `X-Forwarded-Proto`, genera URLs `http://` y puede
entrar en bucle de redirección. Aplicar uno sin el otro es un arreglo a medias.

*(b) Límite de peticiones — riesgo de autobloqueo.* Para un visitante anónimo,
`ThrottleRequests` deriva su clave de `$request->ip()`
(`vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php:229`).
Sin proxies de confianza, `$request->ip()` devuelve la IP del proxy, **no la del
visitante**. Es decir: detrás de un proxy o CDN, los 60 req/min dejarían de ser
*por visitante* y pasarían a ser **un tope global para todo el sitio**. Con
tráfico modesto el sitio empezaría a devolver 429 a todo el mundo. No es un
riesgo teórico: es el comportamiento por defecto en cuanto haya un proxy
delante.

Su valor correcto (la IP del proxy, o `'*'` en una red privada) sólo se sabe
con el host elegido. **Si se despliega detrás de proxy, esto se configura
ANTES de abrir al público, o se sube el límite de `routes/web.php`.**

**3.3 Configuración nginx de producción.** El `docker/nginx/default.conf`
actual es de desarrollo. Faltan dos reglas:

```nginx
location ~ \.php$ {
    try_files $uri =404;        # no pasar a FPM rutas .php inexistentes
    # ...
}
location ^~ /storage/ {         # /storage es el destino de las subidas
    location ~ \.php$ { deny all; }   # nunca ejecutar PHP ahí
}
```

Irrelevante si el host acaba siendo Apache o un PaaS.

**3.4 `php.ini` de producción.** El actual (`docker/php/php.ini`) es de
desarrollo: `display_errors = On` y `opcache.validate_timestamps = 1`. En
producción: `display_errors = Off`, `opcache.validate_timestamps = 0`.

**3.5 `docker-compose.prod.yml`.** El compose actual publica MySQL en el puerto
`33061` del host. En producción **no puede quedar expuesto**: el servicio `db`
no debe mapear puertos. También sobra el servicio `node`.

**3.6 Copias de seguridad de la base de datos.** No existe ninguna estrategia.
Para una liga cuyos datos se cargan a mano durante toda una temporada, esto es
lo que más duele si falla. Definir frecuencia, destino y — importante —
**probar una restauración**.

**3.7 SMTP real.** Hoy `MAIL_MAILER=log`. No hay ningún flujo de correo, así
que no bloquea. Pero si algún día se activa el restablecimiento de contraseña,
hay que configurar SMTP **antes**: con `log`, el enlace de reseteo se escribe
en texto plano en el archivo de log.

**3.8 Content-Security-Policy.** Excluida a propósito, no olvidada. Filament y
Livewire emiten `<script>` y `<style>` en línea; una CSP correcta exige
propagar nonces por el pipeline de assets de Filament, y una incorrecta rompe
el panel en silencio. Retomar cuando haya margen para probarlo en serio.

**3.9 `npm audit` quedó sin ejecutar.** No es un defecto del repo: el endpoint
`quick` de npm 10 está retirado (400) y el endpoint `bulk` de npm 11 devuelve
`503 — "We are currently performing maintenance"`. Se confirmó reproduciendo el
mismo fallo en un proyecto de prueba limpio. **Volver a intentarlo antes de
desplegar.** (`composer audit` sí se ejecutó: cero advisories.)

---

## 4. Pendiente — trabajo ya decidido

No depende del hosting. Son compromisos adquiridos, con su disparador.

**4.1 Políticas por registro y visibilidad por recurso. ⭐ Máxima prioridad.**

*Disparador: la llegada del rol `técnico`.*

Hoy cualquier usuario del panel puede editar cualquier fila, lo cual es
inofensivo con un único operador. En cuanto entre el técnico, deja de serlo.

Ésta es también la razón por la que `User::canAccessPanel()` devuelve `true` y
**no** debe convertirse en un `is_admin`: el técnico *necesita* abrir el panel,
así que cualquier puerta ahí tendría que dejarle pasar igualmente. Su límite
pertenece a una capa más abajo:

| Capa | Pregunta | Estado |
|---|---|---|
| `canAccessPanel()` | ¿puede abrir el panel? | hecho — `true` |
| Visibilidad de recursos | ¿qué ve en su navegación? | **pendiente** |
| Policies (`viewAny`, `update`, `delete`…) | ¿puede tocar *este* registro? | **pendiente** |

**4.2 Rangos de validación** (`fix(validation)`, aparte). Huecos de integridad
de datos, ninguno explotable: `founded_year` sin rango, `capacity` de estadio
admite negativos, `matchdays.number` sin mínimo, ningún campo de texto con
`maxLength` frente al `varchar(255)` de la base de datos.

**4.3 `players.birth_date`.** Hoy no se usa, no se cifra y no se muestra en
ninguna vista pública. Cuando algo le dé un uso, decidir entonces si hace falta
guardarlo.

**4.4 Caché de la clasificación.** `StandingsService` recalcula la tabla entera
en cada petición. El límite de 60 req/min acota el daño; no lo elimina.

**4.5 2FA en el panel.** Filament v5 lo soporta de fábrica.

---

## 5. Descartado a propósito

Registrado para que no se vuelva a plantear sin saber que ya se decidió.

| Descartado | Razón |
|---|---|
| Cifrar columnas (`players.birth_date`) | Los datos de jugadores son públicos por naturaleza. Cifrar haría la columna no ordenable ni filtrable a cambio de ninguna amenaza que este proyecto enfrente. |
| Captcha / protección anti-bot en el login | El sitio público **no tiene ni un formulario**. El único del proyecto es el login del panel, ya limitado a 5 intentos, con un solo usuario legítimo. |
| Purgar secretos del historial de git | **No hay nada que purgar** — verificado sobre todo el historial. Reescribirlo cambiaría todos los hashes de commit a cambio de nada. |
| Row-level security / "public DB key" | Conceptos de Supabase/Firebase. Aquí el navegador nunca habla con MySQL, y MySQL 8 no tiene RLS. El equivalente real es 4.1. |
| Ocultar API keys | El proyecto no consume ninguna API de terceros. |

---

## 5. Operativa del host de contenedores (Vercel)

El hosting ya está decidido (contenedor en Vercel, `Dockerfile.vercel`). Estos
dos puntos salieron al desplegar el cambio de jornadas por división el
2026-09-20; ambos están tratados, con lo que queda fuera del alcance de la
aplicación señalado como tal.

**5.1 Las migraciones se aplican al arrancar el contenedor.** El entrypoint
ejecuta ahora, en este orden: `config:cache`, y después las migraciones.
Cubierto por `ContainerDeployTest`.

Se usa `migrate --force --isolated`. El cerrojo de `--isolated` vive en el
almacén de caché, y producción lo tiene en la base de datos
(`CACHE_STORE=database`), así que es un cerrojo **compartido entre instancias**:
si el host levanta varias a la vez, una migra y el resto sigue sin tocar el
esquema. La rama sin cerrojo del entrypoint existe sólo para una base recién
creada, que aún no tiene tabla `cache_locks`.

Lo que esto compra: el fallo que motivó el cambio era **silencioso**. Con
`matchdays.division_id` sin crear, Eloquent leyó la columna como null en cada
jornada en vez de reventar, así que `/partidos` devolvía 200 sin un solo
partido y en los logs no había nada.

El paso se reintenta tres veces antes de rendirse. El motivo es el escalado a
cero: el contenedor arranca muchas veces al día, y sin reintentos un parpadeo
de la base gestionada durante un arranque en frío no sería una página con
error, sería el sitio entero caído hasta el siguiente intento. Agotados los
tres, el contenedor sale con código 1 a propósito.

Verificado con la imagen construida en local, contra el MySQL de desarrollo:

| Caso | Resultado |
|---|---|
| Base recién creada, sin tabla `migrations` | Rama sin cerrojo: migra las 16 desde cero y el sitio responde (404, que es lo correcto sin temporadas cargadas) |
| Base ya migrada | `migrate --force --isolated` toma el cerrojo y no hay nada que aplicar |
| Base inalcanzable | Tres intentos, mensaje `entrypoint: no se pudieron aplicar las migraciones` y salida con código 1 |

Cada intento contra una base inalcanzable tarda lo que tarde PDO en rendirse
(~40 s midiendo con un host inexistente), así que el peor caso son un par de
minutos antes de que el contenedor muera. Con la base caída el sitio está roto
de todos modos.

Lo que esto cuesta, y conviene tener presente: una migración destructiva se
aplica sola en cuanto se despliega, y **volver atrás el código no vuelve atrás
el esquema**. Para una reversión hay que ejecutar el rollback a mano:

```bash
docker compose exec -T app sh -c 'set -a; . ./.env.tidb; set +a; \
  DB_DATABASE=liga_amazon php artisan migrate:rollback --step=1 --force'
```

(`.env.tidb` trae el usuario administrador; su `DB_DATABASE` apunta a la base
desechable de tests, de ahí que se sobrescriba.)

**5.2 Arranque en frío.** El host escala a cero tras 5 minutos sin tráfico, así
que la primera petición después de un rato de calma paga el arranque entero.
Medido el 2026-09-20:

| Tramo | Medida |
|---|---|
| Primera petición tras 8 minutos de silencio | **sin respuesta en 180 s** (se agotó el `--max-time`; las peticiones inmediatamente posteriores fueron 200 en ~1 s) |
| Primeras peticiones durante una ventana de despliegue | 60–95 s |
| Estado asentado, huecos de 1 minuto entre peticiones | 0,3–0,7 s |
| Contenedor local, imagen ya presente: `docker run` → primer 200 | 4,2 s |
| Entrypoint completo contra el TiDB de producción | 4,6 s (`config:cache` 0,75 · `migrate:status` 1,62 · `migrate --isolated` 2,26) |
| Lo mismo, migrando una base vacía desde cero | ~45 s (una sola vez en la vida de la base) |

La lectura: **la aplicación aporta unos 8 s** del arranque (4,2 de contenedor más
4,6 de entrypoint). El resto —más de dos minutos y medio— es aprovisionamiento
del host y descarga de la imagen (~340 MB en amd64: 203 MB la aplicación con su
`vendor`, 60 MB las extensiones de PHP, 58 MB el binario de FrankenPHP). Se
descartó una a una cada sospecha propia:

- No es la ruta: se colgaban `/`, `/partidos`, `/goleadores` o `/noticias`
  indistintamente, según cuál cayera en una instancia nueva.
- No es PHP arrancando: en una instancia ya levantada, `/up` (PHP sin base) y
  `/partidos` (PHP con base) responden igual de rápido, 0,32 s y 0,56 s.
- No son las migraciones del entrypoint: 4,6 s contra el TiDB real, medidos con
  las credenciales de producción.
- No son los permisos del usuario de la aplicación: su rol incluye `CREATE`,
  `ALTER`, `DROP` e `INDEX`, así que puede migrar.

Aplicado por nuestra parte: `route:cache` y `view:cache` se hornean en la imagen
(ninguno lee entorno: las rutas no usan `env()` y el panel cuelga de un path
fijo), así que dejan de pagarse en cada arranque. Es ~0,9 s menos por arranque;
no mueve la aguja de los tres minutos, y decir lo contrario sería mentir sobre
la medición.

Lo que sí la movería está fuera de la aplicación y necesita una decisión. Con
tres minutos de espera, un visitante que llegue tras un rato de calma se va
antes de ver nada, así que esto **no es cosmético**:

- Mantener una instancia caliente: configuración de escalado del proyecto en
  Vercel, o un cron externo golpeando el sitio cada pocos minutos. Es la única
  solución real al escalado a cero, y se paga en cómputo permanente.

**Decidido el 2026-09-20: se acepta tal cual.** El sitio lo usa un grupo reducido
que ya cuenta con ello, así que pagar cómputo permanente no compensa. Esto no es
un pendiente olvidado; es una decisión tomada con el número delante. Si algún día
el público deja de ser reducido, lo de arriba es por dónde se retoma.
- Seguir adelgazando la imagen. El grueso es `vendor` con Filament dentro;
  recortar ahí es trabajo de horas para ganar decenas de MB.
