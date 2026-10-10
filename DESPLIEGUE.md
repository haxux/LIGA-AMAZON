# Despliegue — Liga Amazon

El sitio vive en **Hostinger**, alojamiento compartido con hPanel, en el dominio
`ligaamazon.com`. Se mudó aquí desde Vercel el **2026-10-10**.

Este documento describe el despliegue tal como está hecho y verificado, no como
se imagina. Lo que no se ha probado se dice que no se ha probado.

---

## 1. La máquina

| | |
|---|---|
| Host | `us-imm-web487.main-hosting.eu` — `212.85.29.1` |
| Usuario | `u406031460` |
| SSH | puerto **65002**, sólo por clave |
| PHP | **8.4** — se elige en hPanel → Avanzado → Configuración PHP |
| Base de datos | **MariaDB 11.8**, en la misma máquina |
| Composer | 2.9.8, en `/usr/local/bin/composer` |
| Git | 2.47.3 |
| Node / npm | **no hay** — ver §3 |

Hay instaladas de PHP 5.2 a 8.6, pero **la que sirve la web es la que diga
hPanel**, no la del `$PATH` de SSH. Son cosas distintas y se han visto
discrepar: por SSH respondía 8.3 mientras los binarios de 8.4 estaban ahí. Para
cualquier comando de Artisan conviene nombrar el binario explícitamente:

```bash
/opt/alt/php84/usr/bin/php artisan ...
```

### Dónde está cada cosa

```
~/domains/ligaamazon.com/
├── app/                      # el repositorio, con vendor/ y .env
├── public_html -> app/public # ENLACE SIMBÓLICO, ver §2
└── public_html.hostinger-original/   # lo que Hostinger dejó puesto, intacto
```

---

## 2. El document root, que es lo único delicado

Hostinger sirve el dominio desde `public_html`. Laravel **tiene que** servirse
desde su carpeta `public/`: si se sirviera la raíz del proyecto, el `.env` con
las contraseñas quedaría descargable desde el navegador.

La solución aplicada es que `public_html` sea un **enlace simbólico** a
`app/public`:

```bash
cd ~/domains/ligaamazon.com
mv public_html public_html.hostinger-original      # una sola vez
ln -sfn ~/domains/ligaamazon.com/app/public public_html
```

**Apache lo sigue** — comprobado el 2026-10-10 sirviendo la página de
clasificación. Se eligió esto frente a la alternativa habitual (dejar en
`public_html` el contenido de `public/` y reapuntar su `index.php`) porque no
toca ningún fichero del repositorio y no hay que repetirlo en cada despliegue.

El directorio original se conserva: si algún día el enlace deja de funcionar, se
vuelve atrás renombrándolo.

---

## 3. Los assets se compilan FUERA del servidor

**No hay Node ni npm en Hostinger.** `public/build` está en `.gitignore`, así
que no viaja con el repositorio: hay que compilarlo en local y subirlo.

```bash
npm ci
npm run build
rsync -az --delete -e "ssh -i ~/.ssh/liga_hostinger -p 65002" \
  public/build/ u406031460@212.85.29.1:~/domains/ligaamazon.com/app/public/build/
```

Vite 8 exige **Node ≥ 20**. Con Node 18 falla con un error que no menciona la
versión por ningún lado:

```
SyntaxError: The requested module 'node:util' does not provide an export named 'styleText'
```

**Olvidar este paso no rompe el sitio, lo deja sin estilos**, que es peor:
responde 200 y parece que funciona.

---

## 4. Lo que corre solo

No hay worker ni scheduler en proceso. Lo único programado es `/cron/tick`, que
anula partidos vencidos y manda recordatorios. Se configura en hPanel →
Avanzado → Trabajos cron, **una vez al día**:

```bash
cd /home/u406031460/domains/ligaamazon.com/app && curl -s \
  -H "Authorization: Bearer $(grep '^CRON_SECRET=' .env | cut -d= -f2)" \
  -H "Host: ligaamazon.com" http://127.0.0.1/cron/tick > /dev/null
```

El secreto se lee del `.env` al ejecutarse, así que **no queda escrito en el
panel**. Sin la cabecera, la ruta responde 403 — comprobado.

---

## 5. Desplegar un cambio

```bash
ssh -i ~/.ssh/liga_hostinger -p 65002 u406031460@212.85.29.1
cd ~/domains/ligaamazon.com/app
P=/opt/alt/php84/usr/bin/php

git pull --ff-only
$P /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction
$P artisan migrate --force
$P artisan config:cache && $P artisan route:cache && $P artisan view:cache
```

Y desde local, **si cambió algo de `resources/`**, los assets (§3).

### Antes de cada despliegue

```bash
composer audit      # cero advisories
php artisan test    # suite completa en verde
```

### Por qué `route:cache` aquí sí y en Vercel no

Livewire deriva el prefijo de sus endpoints de `APP_KEY`
(`EndpointResolver::prefix()`). En Vercel el caché se horneaba en el build,
donde `APP_KEY` todavía no existía, y el panel se quedaba sin JavaScript en
silencio. Aquí el `.env` ya existe cuando se cachea, así que no se da el
problema. Comprobado: `livewire.min.js` responde 200.

---

## 6. La base de datos

MariaDB en `localhost`. La conexión es `mariadb`, no `mysql`: Laravel trae un
driver propio desde la 11 que conoce sus diferencias.

**Se pobló con migraciones y seeder, no restaurando un volcado** — y a
propósito: el volcado venía de TiDB, con su sintaxis, y las migraciones generan
el DDL que corresponda al motor de destino.

```bash
$P artisan migrate --force
$P artisan db:seed --class=DatosBaseSeeder --force
```

`DatosBaseSeeder` siembra la liga entera desde
`database/seeders/data/liga-base.json`, nombrando todo por su clave natural. No
trae usuarios: el administrador se crea pasándole `SEED_ADMIN_EMAIL` y
`SEED_ADMIN_PASSWORD`, y los técnicos se dan de alta en `/admin`.

### Copias de seguridad

**No hay copias automáticas todavía.** Es lo primero pendiente de §8. A mano:

```bash
cd ~/domains/ligaamazon.com/app
MYSQL_PWD=$(grep '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d "'") \
mysqldump -h localhost -u "$(grep '^DB_USERNAME=' .env | cut -d= -f2)" \
  --single-transaction --default-character-set=utf8mb4 --complete-insert --hex-blob \
  "$(grep '^DB_DATABASE=' .env | cut -d= -f2)" | gzip -9 > ~/respaldo-$(date +%Y%m%d).sql.gz
```

Bájalo de la máquina: una copia que vive en el mismo disco que el original no es
una copia.

---

## 7. Lo que protege la aplicación

Implementado y cubierto por tests:

| Control | Dónde | Test |
|---|---|---|
| Sin alta de usuarios fuera del CLI y del panel | panel sin registro | `PanelAccessTest` |
| Subidas limitadas a jpeg/png/webp/gif, 2 MB — **SVG rechazado** | `TeamForm`, `NewsForm` | `UploadValidationTest` |
| Cabeceras de seguridad en todas las respuestas | `SecurityHeaders` | `SecurityHeadersTest` |
| HSTS sólo sobre HTTPS, sin `preload` | `SecurityHeaders` | `SecurityHeadersTest` |
| Rutas públicas a 60 req/min por IP | `routes/web.php` | `SecurityHeadersTest` |
| Plantilla de producción segura por construcción | `.env.production.example` | `ProductionEnvTemplateTest` |
| `/admin` fuera de los buscadores | `public/robots.txt` | — |

Del framework: login con límite de intentos, CSRF, cookies cifradas, bcrypt
coste 12, Eloquent parametrizado, Blade escapado, `#[Fillable]` en los modelos.

---

## 8. Pendiente

1. **Copias de seguridad automáticas** y fuera de la máquina. Hoy no hay
   ninguna.
2. **HTTPS**: hPanel emite certificado gratis en cuanto el dominio resuelva.
   Mientras tanto `APP_FORCE_HTTPS=true` genera URLs `https://` que todavía no
   sirven.
3. **Los escudos de los clubes**. Vivían en un bucket de Cloudflare R2 y no se
   migraron: la base guarda sus rutas pero los ficheros no están, así que salen
   rotos hasta que se vuelvan a subir desde `/admin`.
4. **Un `git pull` no reinicia nada**, pero tampoco limpia el OPcache del
   servidor web. Si un cambio de PHP no se refleja, es eso; no se ha dado
   todavía, y la forma de forzarlo en este host está sin averiguar.

---

## 9. Lo que se dejó atrás

Del despliegue en Vercel, retirado el 2026-10-10:

- `vercel.json`, `Dockerfile.vercel`, `Caddyfile` y `docker/vercel/`.
- El bucket **Cloudflare R2** y sus credenciales. Existía porque el disco de
  Vercel era efímero y cada despliegue se llevaba las imágenes; aquí hay disco
  propio, así que las subidas van a `storage/app/public` vía `storage:link`.
- **TiDB Cloud** y su CA de SSL.
- La sonda barata de migraciones del entrypoint, que existía porque el
  contenedor escalaba a cero y arrancaba decenas de veces al día. Aquí el
  proceso no se apaga.

Nada de eso vuelve. Si algún día hiciera falta almacenamiento de objetos, el
disco `s3` sigue declarado en `config/filesystems.php`, sin usar.
