# Despliegue — Liga Amazon

El sitio vive en **Hostinger**, alojamiento compartido con hPanel, en el dominio
`lietoernooien.com`. Se mudó aquí desde Vercel el **2026-10-10**.

El **2026-10-11** se rehízo en una cuenta nueva. La primera —`u406031460`, con
el dominio `ligaamazon.com`— quedó abandonada: aquel nombre contenía «amazon» y
eso ya había dado problemas por aludir a la empresa. El dominio de ahora es
neerlandés a propósito, porque la liga se muda a Holanda.

Este documento describe el despliegue tal como está hecho y verificado, no como
se imagina. Lo que no se ha probado se dice que no se ha probado.

---

## 1. La máquina

| | |
|---|---|
| Host | `us-imm-web909.main-hosting.eu` — `109.106.251.174` |
| Usuario | `u269987465` |
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
~/domains/lietoernooien.com/
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
cd ~/domains/lietoernooien.com
mv public_html public_html.hostinger-original      # una sola vez
ln -sfn ~/domains/lietoernooien.com/app/public public_html
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
php artisan view:clear      # IMPRESCINDIBLE, ver abajo
npm run build
rsync -az --delete -e "ssh -i ~/.ssh/liga_hostinger -p 65002" \
  public/build/ u269987465@109.106.251.174:~/domains/lietoernooien.com/app/public/build/
```

### Los estáticos de `public/` se cachean una semana

Lo que no pasa por Vite —`public/js/push.js`, `public/sw.js`— se sirve con
`cache-control: public, max-age=604800` y con una URL que nunca cambia. Un
despliegue que los toque es **invisible durante siete días**.

Pasó el 2026-10-13 y costó un despliegue entero de depuración: en el servidor
`public/js/push.js` tenía 6264 bytes con la corrección puesta, y

```bash
curl -s https://lietoernooien.com/js/push.js | wc -c   # 4160, el de la semana anterior
curl -s 'https://lietoernooien.com/js/push.js?x=1' | wc -c   # 6264, el nuevo
```

Por eso el `<script>` lleva pegada la marca de modificación del fichero
(`?v=filemtime`), igual que Vite hace con el CSS, y vive en el componente
`resources/views/components/push-script.blade.php` para que no haya dos
versiones de la misma línea.

**`sw.js` no puede llevar versión**: el ámbito del service worker depende de su
URL. Se registra con `updateViaCache: 'none'`, que obliga al navegador a
pedirlo siempre al servidor.

Para comprobar que un estático llegó de verdad, **siempre con y sin parámetro**:
si difieren, lo que ven los navegadores es el viejo.

### El `view:clear` no es higiene, cambia lo que se publica

`resources/css/app.css` trae del andamiaje de Laravel esta línea:

```css
@source '../../storage/framework/views/*.php';
```

Es el **caché de Blade compilado**, y ahí acaba todo lo que se haya renderizado
en local —incluido el panel de Filament, que tiene su propia hoja aparte y no
necesita nada de la pública. Así que el tamaño del CSS depende de por qué
páginas se haya navegado antes de compilar, y eso no se nota al mirarlo.

Medido el 2026-10-13, con el mismo código y sin cambiar una línea:

| caché de vistas | CSS publicado |
|---|---|
| 140 vistas compiladas | 61,26 kB |
| vacío (`view:clear`) | 43,65 kB |

**17,6 kB —un 40 %— de clases del panel colándose en la hoja pública.** Como el
código fuente de las vistas sí lo escanea Tailwind por su cuenta, el build
limpio es el completo: se comprobó clase por clase que no faltara ninguna de las
que usan las páginas públicas.

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
cd /home/u269987465/domains/lietoernooien.com/app && curl -s \
  -H "Authorization: Bearer $(grep '^CRON_SECRET=' .env | cut -d= -f2)" \
  -H "Host: lietoernooien.com" http://127.0.0.1/cron/tick > /dev/null
```

El secreto se lee del `.env` al ejecutarse, así que **no queda escrito en el
panel**. Sin la cabecera, la ruta responde 403 — comprobado.

---

## 5. Desplegar un cambio

**Lo normal es `scripts/desplegar.sh`**, que hace todo lo de abajo en orden y
comprueba el sitio al terminar. Se niega a arrancar si hay cambios sin commitear
o si tu rama no coincide con `origin/main`, que son las dos formas de desplegar
algo que no es lo que crees. Con `--sin-pruebas` se salta la suite.

Existe porque la receta tiene tres trampas que ya costaron un despliegue cada
una, y escritas a mano se olvidan: el `view:clear` antes de compilar, el
`--no-scripts` de Composer, y no correr nunca el seeder.

A mano, si hace falta:

```bash
ssh -i ~/.ssh/liga_hostinger -p 65002 u269987465@109.106.251.174
cd ~/domains/lietoernooien.com/app
P=/opt/alt/php84/usr/bin/php

git pull --ff-only
$P /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
$P artisan package:discover
$P artisan migrate --force
$P artisan config:cache && $P artisan route:cache && $P artisan view:cache
```

El `--no-scripts` y el `package:discover` a mano son obligatorios: **este PHP
tiene `proc_open` deshabilitado**, así que Composer no puede lanzar ningún
script posterior y revienta con

```
The Process class relies on proc_open, which is not available on your PHP installation.
```

Lo traicionero es *cuándo* revienta: el autoload ya se ha generado y lo que
queda sin hacer es el `package:discover`, de modo que el despliegue parece
completo y lo que queda viejo es el manifiesto de paquetes. Encima Composer
imprime la sinopsis del comando detrás del error, que tapa el mensaje. Si se
desplegó sin esto, basta con correr `$P artisan package:discover` y volver a
cachear.

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

**Sólo para la carga inicial.** Sobre una base que ya tiene la liga, el seeder
se niega a sembrar (sí crea el administrador si se le dan sus variables):
busca a cada jugador por su club y su nombre, así que uno traspasado después
de la instantánea acabaría con una segunda ficha en su club viejo. Forzarlo es
`SEMBRAR_SOBRE_DATOS=1`, y casi nunca es lo que se quiere.

### Copias de seguridad

**No hay copias automáticas todavía.** Es lo primero pendiente de §8. A mano:

```bash
cd ~/domains/lietoernooien.com/app
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
| Salir de una puerta (`/admin` o `/club`) no cierra la otra | `GuardLogoutController` | `LoginLifecycleTest` |
| Tras entrar sólo se sigue una URL pendiente si es de esa puerta | `PanelLoginResponse` | `LoginLifecycleTest` |
| Tope de 10 fallos / 15 min por cuenta y puerta, además del de 5/min por IP; fallos al log (`login.failed`, `login.locked`) | `App\Filament\Auth\Login` | `LoginLifecycleTest` |
| Contraseñas nuevas de 10 caracteres como mínimo | `UserForm` | — |

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
5. **El dominio `lietoernooien.com` no está registrado.** Comprobado contra
   Verisign el 2026-10-11: está libre. Hostinger deja montar el alojamiento sin
   que el dominio sea tuyo, así que el sitio no es alcanzable desde fuera hasta
   comprarlo. Mientras tanto se prueba con `curl -H "Host: lietoernooien.com"`
   contra la IP.
6. **Las cuentas de técnico**, cinco, hay que darlas de alta en `/admin`. No
   viajaron de la base vieja: el seeder no trae usuarios a propósito, porque
   este repositorio es público.
7. **El saldo inicial de los clubes** está a cero en todos.
8. **Aplazado por el propietario (2026-10-11):** limpiar la cuenta vieja
   `u406031460` —borrar allí la base de datos y el `.env`, que llevan
   credenciales— y **renombrar la liga**, que dentro de la aplicación sigue
   llamándose «Liga Amazon» aunque se mude a Holanda. Lo segundo conviene
   hacerlo antes de publicar.

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
