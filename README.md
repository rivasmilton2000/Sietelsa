# Sietelsa Online by Milton

 `sietelsaonline.com`

## Requisitos
- PHP 8.1+
- Apache con `mod_rewrite`, `mod_headers`, `mod_expires`, `mod_deflate` (y opcional `mod_brotli`)
- MySQL 8+
- Extensiones PHP: `pdo_mysql`, `mbstring`, `json`, `fileinfo`, `gd` (recomendado)

## Estructura principal
- `index.php`: sitio público
- `pages/pages_admin/`: dashboard y módulos admin
- `include/`: lógica de negocio, seguridad, DB, utilidades
- `config/app.php`: configuración base de aplicación
- `database/`: backups SQL generados
- `deploy/scripts/`: utilidades operativas

## Variables de entorno
1. Copia `.env.example` a `.env`
2. Completa valores reales

Variables críticas:
- `ENV` (`production`/`development`) valor principal de entorno
- `APP_ENV` (`production`/`development`)
- `INSTALL_ENABLED` (`false` por defecto, solo `true` para tareas puntuales de instalacion en localhost)
- `APP_URL`
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY`
- Variables SMTP (`SMTP_*`)
- GeoIP de visitas (`SIETELSA_VISITAS_GEOIP_*`) para resolver país por IP cuando el servidor no entrega headers `GEOIP_*` / `CF-IPCountry`

## Levantar en local
1. Crear DB y usuario MySQL.
2. Configurar `.env`.
3. Apuntar virtualhost Apache a `/var/www/html`.
4. Habilitar rewrite y headers:
   - `a2enmod rewrite headers expires deflate`
5. Reiniciar Apache.
6. Abrir:
   - Sitio: `/`
   - Admin: `/admin/login`

## Migraciones y setup inicial
- Ejecutar migraciones manuales por CLI:
  - `php tools/migrate.php`
- El login/admin ya no ejecuta migraciones en runtime.
- Si no existe ningun admin activo, usar:
  - `/admin/setup-admin`
- `setup-admin` e `instalar-bd` solo funcionan cuando:
  - `INSTALL_ENABLED=true`
  - Acceso desde `localhost` (`127.0.0.1`/`::1`)
- El admin creado en setup queda con `must_change_password=1` y debe cambiar clave en primer login.

## Deploy en VPS (manual)
1. Respaldar base de datos:
   - `bash deploy/scripts/backup_db.sh`
2. Subir cambios al servidor.
3. Verificar permisos de escritura:
   - `logs/`, `database/`, `assets/cache/img/`, `assets/uploads/usuarios/`
4. Limpiar cache de app:
   - `bash deploy/scripts/clear_cache.sh`
5. Validar Apache:
   - `apachectl -t`
6. Reiniciar Apache:
   - `systemctl reload apache2`

## Comandos útiles
- Backup DB: `bash deploy/scripts/backup_db.sh`
- Limpiar cache: `bash deploy/scripts/clear_cache.sh`
- Rotar logs: `bash deploy/scripts/rotate_logs.sh`
- Backfill de países en visitas históricas: `php tools/backfill_visitas_pais.php --pagina=inicio --batch=100 --max=5000`

## Seguridad aplicada
- CSRF en formularios críticos (público y admin)
- Cookies de sesión seguras (`HttpOnly`, `SameSite`, `Secure` bajo HTTPS)
- Rate limiting básico para login admin
- Headers de seguridad (CSP, X-Frame-Options, nosniff, Referrer-Policy)
- Manejo de errores en producción con logging a archivo

## SEO aplicado
- `title` y `meta description` dinámicos por sección principal
- Canonical por URL pública
- `robots.txt` + `sitemap.xml` con `lastmod`
- JSON-LD `LocalBusiness`
