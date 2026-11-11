# Restricciones del hosting

Documento de referencia. La web y el plugin de competiciones corren en **hosting
compartido de bajo coste**. Trata estas restricciones como el **mínimo obligatorio**:
todo tiene que funcionar aquí, **sin acceso shell, sin procesos en segundo plano y sin
servicios externos**.

> **Entorno de referencia:** OVHcloud Web Hosting **Personal** (Gama Eco), datacenter
> en la UE. Otros clubes usarán el plugin en hosting parecido, así que no asumas nada
> mejor que esto.

Relacionado: [`../RELEASE.md`](../RELEASE.md) (flujo de publicación) y
[`../CLAUDE.md`](../CLAUDE.md) (convenciones del repo).

## Runtime

| Elemento | Valor | Notas |
|---|---|---|
| PHP | 8.4 (8.5 disponible) | El código debe soportar **PHP 8.1+** |
| WordPress | 7.0.x (7.0.2 instalado) | Mínimo del plugin: **6.5** |
| Base de datos | MySQL 8.4, **1 GB máx. por BD** | Sin acceso remoto desde fuera del hosting. Prod: `ctogang635`. Pruebas: `ctogangprueba` (independiente) |
| CPU / RAM | Compartida, hasta 1 vCore / ~1 GB | Peticiones ligeras; pagina y cachea |
| Almacenamiento | 100 GB en total | Las fotos son el mayor consumidor: redimensiona y comprime siempre |
| Caché de objetos | **Sin persistente** (no hay Redis/Memcached) | Usa *transients* (en BD); nunca dependas de un object cache persistente |
| CDN | Ninguno | |
| Límites PHP | `memory_limit`, `max_execution_time`, `upload_max_filesize`: **comprobar en Herramientas → Salud del sitio → Información** | ⚠️ Pendiente: anotar los valores reales aquí |
| Librería de imágenes | GD y/o Imagick: **comprobar en Salud del sitio** | El código debe funcionar con cualquiera (`wp_get_image_editor()`) |
| Cuotas del plan | 15 sitios, 5 BD de 1 GB, 10 buzones, 1 usuario SFTP | Sin shell SSH |

## Qué NO está disponible

- **Sin shell SSH** (solo SFTP): no hay **WP-CLI, Composer, npm ni Git en el servidor**.
  - Nunca requieras ejecutar comandos en el servidor (instalar, migrar, compilar, limpiar caché…).
  - Los cambios de esquema de BD se aplican **automáticamente al cargar el plugin**: se
    guarda `tiro_db_version` en `wp_options`, se compara con la versión del código y se
    corren migraciones con `dbDelta()`. Las migraciones deben ser **aditivas e
    idempotentes**, para que la versión anterior siga funcionando contra el esquema nuevo
    (rollback).
- **Sin procesos largos, colas ni workers.**
  - El trabajo asíncrono va por **WP-Cron** (`wp_schedule_event`). Solo se ejecuta en las
    visitas a páginas, salvo que un cron externo llame a `wp-cron.php`, así que las tareas
    deben ser **cortas, idempotentes y tolerantes a retrasos**.
  - El trabajo pesado (procesar imágenes, recalcular rankings) debe **trocearse** o hacerse
    en línea dentro de los límites de una petición.
- **Sin caché persistente ni almacén en memoria.** Los rankings se cachean con *transients*
  y se invalidan al guardar una puntuación.
- **Sin servicios externos en tiempo de ejecución.**
  - Empaqueta todo el JS/CSS/fuentes **en local**. **Nada de CDNs** (jsDelivr, unpkg, Google
    Fonts…). Dos motivos: RGPD (cargar Google Fonts desde Google es un problema conocido en
    la UE) y que los operadores españoles **bloquean rangos de IP de CDNs compartidos** (p.
    ej. Cloudflare) durante las retransmisiones de LaLiga, lo que rompe webs en España.
  - Correo solo por `wp_mail()`. El sitio configura SMTP (FluentSMTP); nunca envíes correo
    directamente.

## Modelo de despliegue

- El código se despliega con **GitHub Actions por SFTP** (`lftp mirror`). Solo se suben las
  carpetas propias, nunca el núcleo de WordPress.
- El artefacto desplegado debe ser **autocontenido**: `vendor/` (generado con
  `composer install --no-dev`) y los assets compilados (`build/`) van incluidos. **Nada se
  compila en el servidor.**
- `.distignore` excluye ficheros de desarrollo (tests, `src/`, `node_modules/`, configs).
- El despliegue **sustituye carpetas enteras** (`<carpeta>.new` → `<carpeta>`, y la anterior
  queda como `.old` en `.despliegues/`). El código no debe asumir que los ficheros persisten
  entre releases fuera de `wp-content/uploads/`.
- Entornos: **local** (`wp-env`), **pruebas** y **producción**. Se detectan con
  `wp_get_environment_type()`, nunca por el hostname.
  - Carpeta `pruebas/` → `pruebas.ctogandia.com`, con `WP_ENVIRONMENT_TYPE='staging'`.
  - Carpeta `www/` → `ctogandia.com`, con `WP_ENVIRONMENT_TYPE='production'`.
- Hay un **único usuario SFTP** por cuenta de hosting, así que pruebas y producción comparten
  credenciales. Los despliegues a producción están protegidos por **aprobación manual** en
  GitHub.

## Datos y depuración

- **El contenido vive en producción.** Pruebas tiene su **propia BD** (`ctogangprueba`); si
  hace falta probar con datos reales, se *baja* una copia de producción (export/import +
  *search-replace* + sincronizar `uploads/`), **nunca al revés**. No diseñes funciones que
  necesiten empujar datos de BD de pruebas a producción.
- Los ajustes del plugin y los cambios de esquema viajan **en código** (valores por defecto +
  migraciones), no como pasos manuales en el panel.
- Depuración en el servidor: `WP_DEBUG_LOG` más los logs de error del hosting (panel web). No
  hay shell en vivo. Registra contexto útil y evita logs ruidosos.
- Fotos subidas: recodifícalas (esto **elimina los metadatos EXIF/GPS**), redimensiónalas y
  mantenlas **privadas hasta validarlas**.

## Reglas prácticas (para quien contribuya y para asistentes de IA)

1. Si una solución necesita SSH, un *daemon*, Redis, un CDN o un paso de compilación en el
   servidor, está **fuera de alcance**. Busca una alternativa nativa de WordPress.
2. Prefiere las APIs de WordPress: Options, Transients, WP-Cron, REST API, `dbDelta`,
   `wp_get_image_editor`, `wp_mail`, la Privacy API.
3. Toda migración de BD debe ser segura de ejecutar dos veces y segura para hacer rollback.
4. Prueba en pruebas antes de publicar. El merge a `main` (con aprobación) dispara el
   despliegue a producción.
