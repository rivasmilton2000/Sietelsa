# Nota: para que sirve cada modulo (Administracion)

## Modulos de control RBAC/publicacion

- `Aprobacion de modulos`: Permite revisar cada modulo del sistema y cambiar su estado (`BORRADOR`, `PENDIENTE`, `APROBADO`, `RECHAZADO`). Tambien guarda historial de cambios para auditoria.
- `Permisos de modulos`: Permite definir acceso por `perfil` y hacer excepciones por `usuario` (permitir, denegar o heredar del perfil) para cada modulo.

## Modulos de administracion

- `Registrar usuario`: Crea nuevos usuarios del panel administrativo.
- `Editar usuarios`: Actualiza datos de usuarios existentes (perfil, estado, informacion basica, etc.).
- `Eliminar usuarios`: Da de baja/elimina usuarios del sistema segun permisos.
- `Tipos de perfil`: Administra perfiles/roles y su configuracion general de permisos.
- `Gestionar servicios`: Crea, edita o elimina servicios que se muestran en el sitio.
- `Gestionar portafolio`: Administra items del portafolio (contenido e imagenes).
- `Gestionar proyectos`: Administra proyectos publicados (detalle, imagenes y estado).
- `Gestionar nosotros`: Administra la seccion "Nosotros" del sitio.
- `Mensajes contacto`: Revisa y responde mensajes enviados desde el formulario de contacto.
- `Backup BD`: Genera respaldos de base de datos para recuperacion y contingencia.
- `Mantenimiento`: Define la clave unica de mantenimiento (solo admin). Esa misma clave se exige para activar/desactivar mantenimiento y para entrar al modulo. Incluye bitacora (usuario, fecha y hora) y exportacion de reporte en Excel/PDF.

## Nota operativa

- Un modulo puede existir pero no verse en el menu si no esta `APROBADO` o si el usuario/perfil no tiene acceso en `Permisos de modulos`.
