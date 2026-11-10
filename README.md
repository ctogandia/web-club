# web-club

Código propio de la web del **Club de Tiro Olímpico Gandia**: [ctogandia.com](https://ctogandia.com).

WordPress alojado en OVHcloud. Este repositorio **solo** contiene lo que desarrolla el club: el tema hijo, los mu-plugins y los workflows de despliegue. El núcleo de WordPress, los plugins de terceros, los archivos subidos y la base de datos no están aquí.

El plugin de ligas y tiradas está en su propio repositorio: [`tiro-competiciones`](https://github.com/ctogandia/tiro-competiciones).

## Estructura

```
wp-content/
├── mu-plugins/
│   ├── ctogandia-loader.php   # carga todo lo de mu-plugins/ctogandia/
│   └── ctogandia/
│       └── entorno.php        # avisos, correo y noindex según el entorno
└── themes/
    └── kadence-child/         # (próximamente) tema hijo de Kadence
scripts/deploy.sh              # despliegue por SFTP (lftp)
.github/
├── workflows/deploy.yml       # main → pruebas · tag v* → producción
└── actions/sftp-deploy/       # pasos comunes del despliegue
RELEASE.md                     # cómo publicar, volver atrás y refrescar pruebas
```

## Despliegue

- **Push a `main`** → se despliega automáticamente en `pruebas.ctogandia.com`.
- **Tag `v*`** (por ejemplo `v0.1.0`) → se despliega en `ctogandia.com` después de una aprobación manual.

Instrucciones completas en [RELEASE.md](RELEASE.md).

## Configuración de GitHub

- **Secrets del repositorio:** `SFTP_HOST`, `SFTP_USER`, `SFTP_PASSWORD`, `SSH_KNOWN_HOSTS`.
- **Environments:**
  - `staging` → variable `REMOTE_PATH=pruebas`.
  - `production` → variable `REMOTE_PATH=www`, revisor obligatorio y solo tags `v*`.

## Licencia

GPL-2.0-or-later.
