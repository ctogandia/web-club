#!/usr/bin/env bash
#
# Despliega el código de web-club en OVH por SFTP (lftp).
#
# Variables de entorno obligatorias:
#   SFTP_HOST, SFTP_USER, SFTP_PASSWORD  credenciales SFTP de OVH
#   REMOTE_PATH                          "pruebas" o "www"
#
# Cómo funciona:
#   - Las carpetas de DIRS se sustituyen enteras. Primero se suben a
#     .despliegues/<entorno>/<nombre>.new (fuera de la web) y después se
#     renombran: actual → .old, .new → actual. El corte dura milisegundos.
#   - .old se queda en .despliegues/ para poder volver atrás (ver RELEASE.md).
#     Está fuera de wp-content para que WordPress no la muestre como un tema
#     o plugin duplicado.
#   - Los ficheros de FILES se suben tal cual, sobrescribiendo el anterior.
#   - Antes de tocar nada se comprueba que REMOTE_PATH contiene un WordPress.
#
# Uso local (opcional, necesita `brew install lftp`):
#   SFTP_HOST=... SFTP_USER=... SFTP_PASSWORD=... REMOTE_PATH=pruebas scripts/deploy.sh

set -euo pipefail

: "${SFTP_HOST:?Falta SFTP_HOST}"
: "${SFTP_USER:?Falta SFTP_USER}"
: "${SFTP_PASSWORD:?Falta SFTP_PASSWORD}"
: "${REMOTE_PATH:?Falta REMOTE_PATH}"

case "$REMOTE_PATH" in
  www | pruebas) ;;
  *)
    echo "REMOTE_PATH no válido: '$REMOTE_PATH' (solo 'www' o 'pruebas')" >&2
    exit 1
    ;;
esac

# Carpetas que se sustituyen enteras (relativas a la raíz de WordPress).
DIRS=(
  "wp-content/mu-plugins/ctogandia"
  "wp-content/themes/kadence-child"
)

# Ficheros sueltos (relativos a la raíz de WordPress).
FILES=(
  "wp-content/mu-plugins/ctogandia-loader.php"
)

STAGE=".despliegues/${REMOTE_PATH}"

cd "$(dirname "$0")/.."

script="set cmd:fail-exit yes
set net:max-retries 2
set net:timeout 30
set sftp:connect-program 'ssh -a -x -o StrictHostKeyChecking=yes'
open --env-password -u ${SFTP_USER} sftp://${SFTP_HOST}
echo '› Comprobando ${REMOTE_PATH}/wp-config.php'
cls ${REMOTE_PATH}/wp-config.php
mkdir -p -f ${STAGE}
"

for dir in "${DIRS[@]}"; do
  if [[ ! -d "$dir" ]]; then
    echo "· $dir no existe en el repo; se omite"
    continue
  fi
  name="$(basename "$dir")"
  live="${REMOTE_PATH}/${dir}"
  new="${STAGE}/${name}.new"
  old="${STAGE}/${name}.old"
  script+="
echo '› ${dir}'
mkdir -p -f $(dirname "$live")
rm -r -f ${new}
mirror -R --delete --no-perms --parallel=4 --exclude-glob .DS_Store ${dir} ${new}
rm -r -f ${old}
set cmd:fail-exit no
mv ${live} ${old}
set cmd:fail-exit yes
mv ${new} ${live}
"
done

for file in "${FILES[@]}"; do
  if [[ ! -f "$file" ]]; then
    echo "· $file no existe en el repo; se omite"
    continue
  fi
  remote="${REMOTE_PATH}/${file}"
  script+="
echo '› ${file}'
mkdir -p -f $(dirname "$remote")
put ${file} -o ${remote}
"
done

script+="
echo '✓ Despliegue en ${REMOTE_PATH} terminado'
bye
"

export LFTP_PASSWORD="$SFTP_PASSWORD"
lftp -c "$script"
