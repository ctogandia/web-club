# CLAUDE.md — web-club

Instrucciones para Claude Code en este repositorio. Responde siempre en **español**.

## Qué es esto
Código propio de la web del **Club de Tiro Olímpico Gandia** (ctogandia.com): un WordPress alojado en OVHcloud, plan Personal, compartido, **sin SSH ni WP-CLI en el servidor**. Solo hay acceso por SFTP.

Este repo contiene **solo** lo que desarrolla el club:
- `wp-content/mu-plugins/`: el cargador `ctogandia-loader.php` y los mu-plugins en `ctogandia/*.php`.
- `wp-content/themes/kadence-child/`: el tema hijo de Kadence. Todavía no existe.
- `config/`: exportaciones JSON de configuración (Kadence, Fluent Forms, Complianz), para importarlas a mano.
- `scripts/deploy.sh`, `.github/`: el despliegue.
- `RELEASE.md`: la checklist de publicación y los cambios manuales de cada versión.

**No** están ni deben añadirse: el núcleo de WordPress, los plugins de terceros, `uploads/`, volcados de la base de datos, `wp-config.php`, `.htpasswd` ni nada con credenciales.

El plugin de ligas y tiradas está en **otro repo** (`ctogandia/tiro-competiciones`). No lo mezcles aquí.

## Entornos y despliegue
| Entorno | `WP_ENVIRONMENT_TYPE` | Carpeta en el servidor | Se despliega con |
|---|---|---|---|
| Local | `local` | — | `wp-env` (opcional) |
| Pruebas | `staging` | `pruebas/` | push a `main` |
| Producción | `production` | `www/` | tag `v*` + aprobación manual |

- **El código sube, el contenido baja.** Nunca propongas copiar la base de datos de pruebas a producción, ni plugins de "push to live".
- Las carpetas listadas en `DIRS` de `scripts/deploy.sh` se sustituyen enteras: se suben a `.despliegues/<entorno>/<nombre>.new` y luego se renombran, de modo que la versión anterior queda como `.old`. Si añades una carpeta desplegable nueva (por ejemplo el tema hijo), añádela a `DIRS`. Si añades un fichero suelto, a `FILES`.
- **No despliegues tú** ni ejecutes `scripts/deploy.sh` contra el servidor. El despliegue lo hace GitHub Actions.
- Los secrets (`SFTP_*`, `SSH_KNOWN_HOSTS`) están en GitHub. **Nunca** escribas credenciales, rutas absolutas del servidor ni nombres de bases de datos en el repo: **el repo es público**.

## Convenciones de código
- PHP compatible con **8.1+**. El servidor usa 8.4 y WordPress 7.x.
- Sigue los WordPress Coding Standards: tabuladores, `array()`, Yoda conditions y escapado (`esc_html`, `esc_attr`, `wp_kses_post`) en toda salida.
- Prefijo **`ctogandia_`** para funciones, hooks y opciones. Usa closures `static function` cuando no haga falta quitar el hook después.
- Todo fichero PHP empieza con `defined( 'ABSPATH' ) || exit;`.
- **Un mu-plugin = un fichero** en `wp-content/mu-plugins/ctogandia/`, con un docblock que explique qué hace y en qué entornos. El cargador los incluye por orden alfabético. No crees ficheros sueltos en `mu-plugins/`.
- Lo que dependa del entorno se decide con `wp_get_environment_type()`, nunca mirando la URL ni la base de datos.
- Los comentarios y textos visibles del sitio van en español. No hace falta i18n en este repo. El plugin `tiro-competiciones` sí usa inglés + i18n.
- Antes de añadir lógica a un mu-plugin, piensa si encaja mejor en el tema hijo (presentación) o en el plugin (funcionalidad de competiciones).

## Antes de dar algo por terminado
```bash
find wp-content -name '*.php' -print0 | xargs -0 -n1 php -l   # lo mismo que hace la CI
bash -n scripts/deploy.sh
```
Si cambias `scripts/deploy.sh`, pruébalo con un `lftp` falso que imprima el script generado y revisa los comandos antes de hacer commit.

## Git
- Commits pequeños, con mensaje en español y en imperativo: `Añade aviso de entorno en la barra de admin`.
- Trabaja en una rama y abre una PR a `main`, salvo en cambios triviales de documentación.
- **No crees tags ni hagas push de tags.** Publicar en producción lo decide y hace Cristobal (ver `RELEASE.md`).
- La identidad de Git y la clave SSH se configuran automáticamente según la carpeta. No cambies `user.*` ni `core.sshCommand`.

## Cuando un cambio necesita pasos manuales
Si un cambio requiere hacer algo a mano en producción (activar un plugin, importar un JSON de `config/`, cambiar un ajuste del panel…), **añádelo a `RELEASE.md`**, en la sección de la versión que corresponda. Los cambios hechos a mano en el servidor se apuntan en un `CHANGELOG.md` que vive en el propio servidor, fuera de este repo.
