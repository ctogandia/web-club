# Cómo publicar cambios

## Entornos

| Entorno | URL | Se despliega con |
|---|---|---|
| Pruebas | https://pruebas.ctogandia.com (con contraseña) | push a `release/rc-*` (auto) o *Run workflow* desde `develop` |
| Producción | https://ctogandia.com | merge a `main`, con aprobación |

**Regla de oro:** el **código sube** por GitHub; nunca se copia una base de datos de pruebas encima de la de producción. Pruebas tiene su **propia base de datos** (`ctogangprueba`), independiente de la de producción (`ctogang635`): desplegar o trastear en pruebas no toca los datos de producción. Si quieres probar con datos reales, *bajas* una copia de producción a pruebas (ver más abajo).

## Flujo

```
develop ──(Run workflow)──────────► pruebas    (a demanda)
   └─ release/rc-vX.Y.Z ──(push)───► pruebas    (automático)
            └─ PR a main ─ check php -l ─► merge ─► PRODUCCIÓN (aprobación) ─► tag vX.Y.Z
```

`main` es producción. Mergear a `main` publica en producción tras tu aprobación y, si el commit de merge conserva el nombre `release/rc-vX.Y.Z`, crea el tag automáticamente.

## Checklist de cada versión

1. [ ] Desde `develop`, crea la rama de release con la versión en el nombre:
   ```bash
   git switch develop && git pull
   git switch -c release/rc-v0.X.Y
   git push -u origin release/rc-v0.X.Y
   ```
   El push despliega en pruebas automáticamente.
2. [ ] Si hace falta probar con datos reales, refresca pruebas con los de producción (ver más abajo).
3. [ ] Prueba en pruebas: la versión móvil y lo que haya cambiado.
4. [ ] Abre un PR de `release/rc-v0.X.Y` → `main`. Espera a que el check **Comprobar PHP** pase.
5. [ ] Haz una copia de seguridad de producción con UpdraftPlus **antes** de aprobar.
6. [ ] Mergea el PR **con merge commit** (no *squash*, para que el nombre de la rama quede en el commit y el release se cree solo).
7. [ ] En *Actions → Desplegar → Review deployments*, aprueba **production**.
8. [ ] Comprueba que se ha creado el **release `v0.X.Y`** en GitHub (pestaña *Releases*), con su changelog automático. Lo hace el workflow tras desplegar.
9. [ ] Aplica a mano los cambios de configuración de la sección de esta versión (abajo).
10. [ ] Revisa la web y los logs de OVH (*Estadísticas y logs*).
11. [ ] Si has cambiado algo en el servidor a mano, apúntalo en el `CHANGELOG.md` de la raíz del servidor.

## Configuración de GitHub (una sola vez)

Esto no está en el repo; se configura en *Settings* del repositorio en GitHub:

- **Environments → `staging`:** variable `REMOTE_PATH = pruebas`. Secrets `SFTP_HOST`, `SFTP_USER`, `SFTP_PASSWORD`, `SSH_KNOWN_HOSTS`.
- **Environments → `production`:** variable `REMOTE_PATH = www`. Mismos secrets. **Required reviewers:** tú (es la aprobación antes de publicar).
- **Branches → regla para `main`:** requiere PR antes de mergear y marca **Comprobar PHP** como check obligatorio.
- **General → Pull Requests:** deja activado *Allow merge commits* y **Allow auto-merge** (lo usa el sync `main → develop`). El tag automático lee el nombre `release/rc-vX.Y.Z` del commit de merge; si mergeas con *squash*, ese nombre se pierde y no se crea el tag (tendrías que tagear a mano).
- **GitHub App `ctogandia-ci`:** creada en la organización, con permisos *Contents*, *Pull requests* y *Workflows* (Read & write), instalada en el repo. Sus credenciales están en variables/secrets de **organización**: variable `APP_ID` y secret `APP_PRIVATE_KEY`. Los workflows `crear-release` y `sync-main-develop` mintan un token con ella (`actions/create-github-app-token`) para crear ramas y PRs que **sí** disparan los demás workflows (el `GITHUB_TOKEN` no lo haría).
- **Regla para `develop`:** para que el sync se auto-mergee, `develop` no debe exigir *reviews* (basta requerir PR + el check **Comprobar PHP**), o bien añade la App `ctogandia-ci` a la lista de *bypass* de su regla de protección.

## Volver atrás (rollback)

**Opción A, volver a desplegar la versión anterior.** Es la recomendada.
En *Actions → Desplegar*, abre la ejecución de producción de la versión anterior (el merge a `main` correspondiente, o el tag `vX.Y.Z` desde *Tags*) y pulsa *Re-run all jobs*. Vuelve a aprobar **production**. Redesplegará ese commit.

**Opción B, inmediata, por SFTP.** Cada despliegue guarda la versión anterior en `.despliegues/<entorno>/<nombre>.old`, en la raíz del alojamiento. Para volver a ella, renombra:
```
www/wp-content/themes/kadence-child           →  .despliegues/www/kadence-child.roto
.despliegues/www/kadence-child.old            →  www/wp-content/themes/kadence-child
```
Lo mismo vale para `mu-plugins/ctogandia`.

## Refrescar pruebas con datos de producción

Copia el estado real de producción a pruebas para probar con datos de verdad. **El
contenido baja (prod → pruebas), nunca al revés.**

### Qué se sobrescribe y qué se mantiene

- **Se sobrescribe (vive en la BD):** todo el contenido (páginas, entradas, menús,
  medios), los **usuarios y contraseñas** (entrarás con los de prod), los ajustes del
  sitio (título, email de admin, zona horaria, permalinks, `siteurl`/`home`), el estado
  activo/inactivo de los plugins y **toda su configuración** (incluidas las credenciales
  SMTP de FluentSMTP y la licencia de Wordfence), y los ajustes del Personalizador.
- **Se mantiene (vive en ficheros):** `pruebas/wp-config.php` (`WP_ENVIRONMENT_TYPE`,
  `DISALLOW_FILE_EDIT`, la BD `ctogangprueba`, las *salts*), los mu-plugins y el tema
  hijo, y `pruebas/.htaccess` (la contraseña). Por eso el **aviso de pruebas, el noindex
  y el bloqueo de correo sobreviven** al refresh: están en ficheros, no en la BD.
- **`uploads/` no viaja con la BD:** hay que copiarlo aparte (paso 5), o las imágenes
  saldrán rotas.

### Preparación (una sola vez)

- En `pruebas/wp-config.php`, fija las URLs para que pruebas **siempre resuelva a sí
  mismo**, aunque la BD traiga las de prod (si no, tras importar no podrías ni entrar al
  panel para arreglarlo):
  ```php
  define( 'WP_HOME', 'https://pruebas.ctogandia.com' );
  define( 'WP_SITEURL', 'https://pruebas.ctogandia.com' );
  ```
- Comprueba que el **`$table_prefix`** de `pruebas/wp-config.php` coincide con el de
  `www/wp-config.php` (normalmente `wp_`). Si no coincide, la importación no encaja:
  iguala el de pruebas al de prod.

### Cada refresco

1. **Exporta la BD de producción** (`ctogang635`) desde phpMyAdmin (panel de OVH) a un
   `.sql`. Haz antes una **copia de seguridad de prod con UpdraftPlus** por si acaso.
2. **Importa en la BD de pruebas** (`ctogangprueba`): en phpMyAdmin, **vacía antes las
   tablas** de `ctogangprueba` y luego importa el `.sql`. Esto borra lo que hubiera en
   pruebas.
3. Entra en `https://pruebas.ctogandia.com/wp-admin` (gracias a `WP_HOME`/`WP_SITEURL`
   ya puedes, aunque la BD tenga las URLs de prod). Las credenciales ahora son **las de
   prod**.
4. **Better Search Replace** (instálalo/actívalo en pruebas): reemplaza
   `https://ctogandia.com` → `https://pruebas.ctogandia.com`, con *todas las tablas*
   marcadas. **Primero en modo simulación**, luego en real. Arregla las URLs incrustadas
   en el contenido y en datos serializados.
5. **Sincroniza `uploads/`** de prod a pruebas por SFTP:
   `www/wp-content/uploads/` → `pruebas/wp-content/uploads/` (p. ej. `lftp mirror`).
6. **Limpieza tras el refresh:**
   - Reactiva el set de plugins de pruebas. Los que solo van en prod (Wordfence,
     LightStart, UpdraftPlus, Rank Math, WP Super Cache) WordPress los **desactiva solos**
     porque sus ficheros no están en pruebas; es normal.
   - Si Wordfence fuera premium, **quita su licencia** de pruebas (viene en la BD de prod)
     para no tener dos sitios con la misma key.
   - Comprueba que aparece el aviso **WEB DE PRUEBAS** (ámbar). Confirma que el `curl` de
     pruebas sigue con *noindex* y que los correos están bloqueados (botón "Correos OFF").

## Qué está en este repositorio y qué no

**Sí:** el tema hijo (`kadence-child`), los mu-plugins del club, las exportaciones de configuración y los workflows.
**No:** el núcleo de WordPress, los plugins de terceros, `uploads/`, la base de datos ni `wp-config.php`.

---

## Cambios de configuración manuales por versión

Apunta aquí lo que hay que hacer a mano en producción al publicar cada versión: ajustes del panel, importar un JSON de configuración, activar un plugin…

### v0.2.0
- **Requiere el tema Kadence (padre) instalado.** Instálalo desde *Apariencia → Temas → Añadir nuevo* (wordpress.org) **antes** de activar el hijo; sin el padre, el tema hijo aparece como roto y no se puede activar.
- Activa el tema **Kadence Child – CTO Gandia** en *Apariencia → Temas*.
- Hazlo primero en pruebas y, al publicar, también en producción.

### v0.1.0
- Nada. Solo instala los mu-plugins de entorno.
- Comprobación: en producción **no** debe aparecer el aviso rojo, y en pruebas **sí**.
