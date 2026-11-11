# Puesta en marcha de la web (panel)

Checklist para quien **monta y configura la web** en el panel de WordPress. Esto es
trabajo de **panel y contenido**, no de código: los plugins son de terceros (se instalan
desde *Plugins → Añadir nuevo*) y lo que configures vive en la base de datos.

> **Apunta lo que vayas haciendo en el `CHANGELOG.md` del servidor** (fuera del repo).
> El código (tema hijo, mu-plugins) ya llega solo por el despliegue; aquí solo van los
> pasos de panel.

Relacionado: [`hosting.md`](hosting.md) (restricciones), [`../RELEASE.md`](../RELEASE.md) (publicar código).

## 1. Usuarios y limpieza

- [ ] Crear un usuario **administrador propio** para cada persona (nunca compartir cuenta).
- [ ] Activar **2FA** (lo da Wordfence).
- [ ] Borrar el admin que generó OVH (`admin6212`) una vez haya otro admin.
- [ ] Borrar lo de ejemplo: entrada "Hola mundo", página de ejemplo, **Hello Dolly** y **Akismet**.
- [ ] Borrar los temas sobrantes (deja Kadence + el hijo, y uno de reserva).

## 2. Ajustes básicos

- [ ] *Ajustes → Generales:* idioma **Español**, zona horaria **Madrid**, formato de fecha `d/m/Y`.
- [x] *Ajustes → Enlaces permanentes:* **"Nombre de la entrada"** (ya hecho en prod y pruebas).
- [ ] *Ajustes → Comentarios:* desactivar comentarios.
- [ ] Activar **actualizaciones automáticas** del núcleo y de los plugins (menores/seguridad).
- [ ] *Ajustes → Lectura → "Disuadir a los motores de búsqueda":* **desmarcado** en producción (la página provisional debe indexarse). En **pruebas** sí, marcado (aunque el mu-plugin de entorno ya fuerza noindex ahí).

## 3. Plugins base (todos gratuitos)

Instala en este orden; los dos primeros son prioridad.

| Plugin | Para qué | Nota |
|---|---|---|
| **UpdraftPlus** | Copias de seguridad | **Lo primero.** Backups automáticos a un Google Drive del club (BD + `uploads/`). Es la red de seguridad: restaurar = este backup + redeploy del código. Prueba una restauración una vez. |
| **Wordfence** | Seguridad + 2FA | |
| **FluentSMTP** | Envío de correo | Conéctalo al buzón `web@ctogandia.com` de OVH. Manda un correo de prueba. |
| **Antispam Bee** | Antispam | Sin cuenta externa, a diferencia de Akismet. |
| **Kadence** + **Kadence Blocks** | Tema y diseño | Kadence es el **tema padre** (necesario para el hijo). Kadence Blocks es la caja de bloques con la que se construyen portada, secciones y la cuadrícula de patrocinadores. |
| **Rank Math** | SEO | |
| **WP Super Cache** | Caché | |
| **Complianz** | Cookies y RGPD/LSSI | Banner + páginas de Aviso legal, Privacidad y Cookies. |
| **The Events Calendar** | Eventos | |
| **Fluent Forms** | Formularios | Contacto e inscripción a la escuela (con consentimiento de imagen). |

Config que **se podrá versionar** luego como JSON en `config/`: Kadence, Fluent Forms, Complianz.

## 4. Tema del club

- [ ] Instalar **Kadence** (padre) desde *Apariencia → Temas → Añadir nuevo*.
- [ ] Activar el tema hijo **Kadence Child – CTO Gandia** (llega por despliegue de Git). Sin el padre, el hijo sale como roto.
- [ ] En *Apariencia → Personalizar*: logo del club, colores (rojo/oro/verde del escudo), tipografía, cabecera y pie.

## 5. Construir la web

- [ ] Páginas: Inicio (portada estática con Kadence Blocks), Sobre el club, Escuela, Contacto, y las legales de Complianz.
- [ ] Categoría **"Anuncios"** para el tablón (entradas).
- [ ] Menú principal y pie.
- [ ] Patrocinadores: cuadrícula de logos (Kadence Blocks).

## 6. Antes de lanzar

- [ ] Probar todos los **formularios** (que llega el correo).
- [ ] Revisar en **móvil** y pasar **PageSpeed**.
- [ ] **Copia con UpdraftPlus.**
- [ ] Quitar la página provisional: desactivar y borrar **LightStart**, **Otter Blocks** y la **página 7**. En el repo, borrar el mu-plugin `ctogandia/coming-soon.php` (fuerza el 200 mientras LightStart está activo) y publicar.
- [ ] Alta en **Google Search Console**.

## Recordatorios

- El **contenido se edita en producción** (WordPress ya tiene borradores y vista previa). Pruebas es para probar *código*; recibe copias de prod cuando hace falta (ver `RELEASE.md`).
- **Menores:** autorización escrita de padres/tutores antes de publicar fotos.
- **Datos de socios:** guardar solo la fecha de caducidad de la licencia, nunca el número ni copias del documento.
- Antes de instalar un plugin nuevo, comprobar que tiene actualizaciones recientes y muchas instalaciones.
