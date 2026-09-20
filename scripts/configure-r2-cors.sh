#!/usr/bin/env bash
#
# Configura CORS en el bucket de R2.
#
# Hace falta porque en produccion las subidas temporales de Livewire van al
# bucket (LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=s3) y no al disco del contenedor,
# que es efimero y distinto en cada instancia. Con disco S3, Livewire firma una
# URL y el NAVEGADOR sube directamente a R2: eso es una peticion entre origenes,
# y sin CORS el navegador la bloquea antes de enviarla. El sintoma es una subida
# que falla sin dejar ni una linea en los logs del servidor, porque el servidor
# nunca llega a enterarse.
#
# Uso:  ./scripts/configure-r2-cors.sh https://tu-dominio
#       sin argumento usa el dominio de produccion actual.
#
# Es idempotente: reescribe la regla entera cada vez.

set -euo pipefail

ORIGEN="${1:-https://liga-amazon.vercel.app}"

if [ ! -f .env.r2 ]; then
    echo "Falta .env.r2 — copiar .env.r2.example y rellenarlo." >&2
    exit 1
fi

set -a
# shellcheck disable=SC1091
. ./.env.r2
set +a

: "${AWS_ENDPOINT:?AWS_ENDPOINT no esta definido en .env.r2}"
: "${AWS_BUCKET:?AWS_BUCKET no esta definido en .env.r2}"

echo "Bucket: ${AWS_BUCKET}"
echo "Origen permitido: ${ORIGEN}"
echo

ORIGEN="$ORIGEN" php -r '
require "vendor/autoload.php";

$cliente = new Aws\S3\S3Client([
    "version" => "latest",
    "region" => getenv("AWS_DEFAULT_REGION") ?: "auto",
    "endpoint" => getenv("AWS_ENDPOINT"),
    "use_path_style_endpoint" => true,
    "credentials" => [
        "key" => getenv("AWS_ACCESS_KEY_ID"),
        "secret" => getenv("AWS_SECRET_ACCESS_KEY"),
    ],
]);

$bucket = getenv("AWS_BUCKET");
$origen = getenv("ORIGEN");

// PUT es la subida firmada de Livewire. GET y HEAD los necesita la vista previa
// que el panel muestra antes de guardar. ETag expuesto porque el cliente de
// subida lo lee para confirmar la escritura.
try {
    $cliente->putBucketCors([
    "Bucket" => $bucket,
    "CORSConfiguration" => ["CORSRules" => [[
        "AllowedOrigins" => [$origen],
        "AllowedMethods" => ["PUT", "GET", "HEAD"],
        "AllowedHeaders" => ["*"],
        "ExposeHeaders"  => ["ETag"],
        "MaxAgeSeconds"  => 3600,
    ]]],
    ]);
} catch (Aws\S3\Exception\S3Exception $e) {
    if ($e->getAwsErrorCode() === "AccessDenied") {
        fwrite(STDERR,
            "ACCESO DENEGADO al configurar CORS.\n\n" .
            "El token de R2 tiene permisos de objetos (Object Read & Write), que bastan\n" .
            "para subir y borrar ficheros pero no para cambiar la configuracion del bucket.\n" .
            "CORS es configuracion del bucket.\n\n" .
            "Dos salidas:\n" .
            "  a) Ponerlo a mano: panel de Cloudflare -> R2 -> el bucket -> Settings ->\n" .
            "     CORS Policy -> Add CORS policy, y pegar la regla que documenta\n" .
            "     DESPLIEGUE.md.\n" .
            "  b) Crear un token con Admin Read & Write y volver a ejecutar esto.\n"
        );
        exit(1);
    }
    throw $e;
}

echo "Regla aplicada. Releyendola del bucket para confirmar:\n\n";

// Lectura de vuelta, no confianza en que el PUT haya surtido efecto.
$actual = $cliente->getBucketCors(["Bucket" => $bucket]);
foreach ($actual["CORSRules"] as $r) {
    echo "  origenes: " . implode(", ", $r["AllowedOrigins"]) . "\n";
    echo "  metodos:  " . implode(", ", $r["AllowedMethods"]) . "\n";
    echo "  expuesto: " . implode(", ", $r["ExposeHeaders"] ?? []) . "\n";
}
'
