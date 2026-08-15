# Seguridad y autenticación

Documentación del sistema de usuarios, login del panel administrativo y controles de seguridad aplicados en el proyecto.

## Documentos

| Documento | Contenido |
| --------- | --------- |
| [autenticacion-admin.md](./autenticacion-admin.md) | Flujo de login, roles, estados, intentos fallidos, throttling, remember me y archivos clave |

## Alcance actual

Esta documentación cubre el **backend administrativo** (`/admin/*`):

- Login con nickname y contraseña
- Control de acceso por rol (`ROLE_ADMIN` mínimo)
- Bloqueo por intentos fallidos y throttling por IP
- Estados de cuenta y redirecciones post-login

El login público del sitio web (`src/Web`) aún no está implementado; la entidad `User` y el rol `user` están preparados para ese contexto futuro.
