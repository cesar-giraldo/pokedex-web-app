# Autenticación del panel administrativo

Este documento describe cómo funciona el login del backend (`/admin`), qué reglas de negocio se aplican, qué componentes intervienen y qué comportamientos debe esperar un desarrollador al extender la funcionalidad.

## Resumen

| Aspecto | Implementación |
| ------- | -------------- |
| Identificador de login | `nickname` (normalizado a minúsculas) |
| Provider | Entidad `App\Entity\User` vía Doctrine |
| Firewall | `main` en `config/packages/security.yaml` |
| Formulario | `POST /admin/login` con CSRF stateless |
| Acceso al backend | Requiere `ROLE_ADMIN` (o superior) |
| Sesión persistente | `remember_me` (7 días, cookie `REMEMBERME`) |

---

## Arquitectura de componentes

```
config/packages/security.yaml     → Firewall, access_control, throttling, remember_me
src/Entity/User.php               → Modelo, intentos fallidos, bloqueo temporal
src/Entity/Enum/UserRole.php      → Roles de aplicación → roles Symfony
src/Entity/Enum/UserStatus.php    → Estados de cuenta y mensajes de denegación
src/Admin/Security/UserChecker.php → Validaciones pre/post autenticación
src/Admin/EventSubscriber/AdminSecuritySubscriber.php → Contador, redirects, perfil incompleto
src/Admin/Controller/SecurityController.php → Pantalla de login
src/Admin/Form/LoginFormType.php  → Campos _username, _password, _remember_me
translations/security.es.yaml     → Mensajes de error de Symfony Security
```

---

## Roles de aplicación

Los roles se almacenan en la columna JSON `users.roles` y se mapean a roles Symfony mediante `UserRole`.

| Rol (`UserRole`) | Rol Symfony | Acceso backend (`/admin`) | Jerarquía Symfony |
| ---------------- | ----------- | ------------------------- | ----------------- |
| `developer` | `ROLE_DEVELOPER` | Sí | Hereda `ROLE_ADMIN` y `ROLE_USER` |
| `admin` | `ROLE_ADMIN` | Sí | Hereda `ROLE_USER` |
| `user` | `ROLE_USER` | **No** | Rol base (sitio público futuro) |

La jerarquía está definida en `security.yaml`:

```yaml
role_hierarchy:
    ROLE_DEVELOPER: ROLE_ADMIN
    ROLE_ADMIN: ROLE_USER
```

`access_control` exige `ROLE_ADMIN` para cualquier ruta bajo `/admin` (excepto login y logout).

Un usuario con rol `user` puede autenticarse con credenciales válidas, pero `AdminSecuritySubscriber` invalida el token y lo devuelve al login con el mensaje *"No tienes acceso al panel de administración."*

---

## Estados de cuenta (`UserStatus`)

| Estado | Valor DB | Permite login backend | Comportamiento |
| ------ | -------- | --------------------- | -------------- |
| `UnconfirmedAccount` | `unconfirmed_account` | No | Mensaje: confirmar cuenta |
| `UncompleteProfileInfo` | `uncomplete_profile_info` | Sí (parcial) | Login OK → redirect a completar perfil |
| `Active` | `active` | Sí | Login OK → redirect a `/admin/pokemons` |
| `Banned` | `banned` | No | Cuenta suspendida |
| `Inactive` | `inactive` | No | Cuenta inactiva |

### Perfil incompleto

Usuarios con estado `UncompleteProfileInfo` y rol admin/developer:

1. Pueden autenticarse (`UserChecker` lo permite en `checkPreAuth` y `checkPostAuth`).
2. Tras login exitoso se redirigen a `app_design_user_profile` (`/admin/ui-kit/profile`).
3. `AdminSecuritySubscriber::onKernelRequest` fuerza esa ruta mientras el perfil no esté completo; solo pueden navegar a rutas permitidas (perfil, logout, assets, profiler).

---

## Normalización de nickname

Todo nickname se normaliza con `User::normalizeNickname()`:

```php
mb_strtolower(trim($nickname))
```

Se aplica en:

- `User::setNickname()` al persistir
- `UserRepository::findOneByNickname()` al buscar (consulta con `LOWER`)
- `AdminSecuritySubscriber::onLoginFailure()` al procesar intentos fallidos

El usuario puede escribir `Admin-Login` o `  admin-login  ` en el formulario; el sistema lo resolverá igual.

---

## Diagrama de flujo del login

Flujo completo desde el `POST /admin/login` hasta la respuesta final.

```mermaid
flowchart TD
    START([POST /admin/login]) --> CSRF{¿Token CSRF válido?}
    CSRF -->|No| ERR_CSRF[Error: token CSRF inválido]
    CSRF -->|Sí| THROTTLE{¿Login throttling<br/>IP + nickname?}
    THROTTLE -->|Superado| ERR_THROTTLE[Error: demasiados intentos<br/>por IP en 15 min]
    THROTTLE -->|OK| LOAD[Cargar usuario por nickname normalizado]

    LOAD --> EXISTS{¿Usuario existe?}
    EXISTS -->|No| ERR_GENERIC[BadCredentialsException<br/>Credenciales inválidas]
    EXISTS -->|Sí| PRE[UserChecker::checkPreAuth]

    PRE --> LOCKED{¿noLoginUntil<br/>en el futuro?}
    LOCKED -->|Sí| ERR_LOCK[Mensaje: esperar X hora(s)]
    LOCKED -->|No| STATUS{¿Estado permite<br/>login backend?}
    STATUS -->|No| ERR_STATUS[Mensaje según estado<br/>ej. cuenta no confirmada]
    STATUS -->|Sí| PWD{¿Contraseña correcta?}

    PWD -->|No| FAIL[LoginFailureEvent]
    PWD -->|Sí| POST[UserChecker::checkPostAuth]

    POST --> POST_OK{¿Estado Active<br/>o UncompleteProfile?}
    POST_OK -->|No| ERR_STATUS
    POST_OK -->|Sí| SUCCESS[LoginSuccessEvent]

    FAIL --> FAIL_RULES{¿Incrementar<br/>failedLoginAttempts?}
    FAIL_RULES -->|Nickname inexistente| NO_INC0[Sin escritura BD]
    FAIL_RULES -->|Bloqueo activo| NO_INC1[Sin escritura BD]
    FAIL_RULES -->|Fallo no es BadCredentials| NO_INC2[Sin escritura BD]
    FAIL_RULES -->|BadCredentials + usuario activo| INC[+1 intento<br/>4.º intento → noLoginUntil +3h]
    INC --> ERR_GENERIC
    NO_INC0 --> ERR_GENERIC
    NO_INC1 --> ERR_LOCK
    NO_INC2 --> ERR_STATUS

    SUCCESS --> RESET[Reset failedLoginAttempts<br/>y noLoginUntil]
    RESET --> ROLE{¿Tiene rol<br/>admin/developer?}
    ROLE -->|No| DENY[Rol user: invalidar token<br/>flash error → login]
    ROLE -->|Sí| PROFILE{¿Estado<br/>UncompleteProfileInfo?}
    PROFILE -->|Sí| REDIR_PROFILE[Redirect → completar perfil]
    PROFILE -->|No| REDIR_ADMIN[Redirect → /admin/pokemons]

    ERR_GENERIC --> END([Vuelta a /admin/login])
    ERR_CSRF --> END
    ERR_THROTTLE --> END
    ERR_LOCK --> END
    ERR_STATUS --> END
    DENY --> END
    REDIR_PROFILE --> END_OK([Sesión iniciada])
    REDIR_ADMIN --> END_OK
```

---

## Reglas de intentos fallidos

Constantes en `User`:

| Constante | Valor | Significado |
| --------- | ----- | ----------- |
| `MAX_FAILED_LOGIN_ATTEMPTS` | `4` | Al cuarto intento fallido se activa bloqueo |
| `LOGIN_LOCK_HOURS` | `3` | Duración del bloqueo (`noLoginUntil`) |

### Reglas de negocio

| # | Regla | Implementación |
| - | ----- | -------------- |
| 0 | Nickname inexistente → denegar sin tocar BD | `onLoginFailure` retorna si no hay usuario |
| 1 | Nickname existe + contraseña incorrecta → +1 intento | Solo si la excepción es `BadCredentialsException` |
| 2 | Login exitoso → reiniciar contador y bloqueo | `resetFailedLoginAttempts()` limpia contador y `noLoginUntil` |
| 3 | Tras 4 intentos fallidos → bloqueo 3 horas | `User::recordFailedLoginAttempt()` |
| 4 | Credenciales correctas + bloqueo activo → denegar con mensaje de espera | `UserChecker::checkPreAuth()` antes de verificar contraseña |
| 5 | Contraseña incorrecta + bloqueo activo → no incrementar | `isLoginTemporarilyBlocked()` retorna antes de incrementar |

> **Orden Symfony:** `checkPreAuth` se ejecuta **antes** de validar la contraseña. Si hay bloqueo temporal, la contraseña no se comprueba y el fallo no cuenta como intento de contraseña incorrecta.

---

## Capas de protección adicionales

### 1. Login throttling (por IP + nickname)

Configurado en el firewall `main`:

```yaml
login_throttling:
    max_attempts: 10
    interval: '15 minutes'
```

Limita intentos agregados por dirección IP y nickname, independientemente del bloqueo por cuenta. Complementa las 4 fallas / 3 h por usuario.

En entorno `test` el límite se relaja (`1000` intentos) para no romper la suite PHPUnit.

### 2. Ocultar usuarios inexistentes (`expose_security_errors`)

Symfony 8 reemplazó `hide_user_not_found` por:

```yaml
expose_security_errors: none
```

Con este valor, un nickname que no existe produce el mensaje genérico **"Credenciales inválidas"**, igual que una contraseña incorrecta.

> Los mensajes personalizados vía `CustomUserMessageAccountStatusException` (bloqueo temporal, cuenta no confirmada, etc.) **sí se muestran** al usuario. Esto puede permitir enumeración parcial de cuentas existentes; es una decisión consciente para dar feedback claro.

### 3. CSRF

- `form_login.enable_csrf: true` con token `authenticate`
- El formulario incluye el token manualmente porque el firewall es stateless respecto al form CSRF de Symfony Form
- `LoginFormType` usa `csrf_protection: false` y `data-turbo: false` en el `<form>`

### 4. Remember me y logout

| Configuración | Valor |
| ------------- | ----- |
| Cookie | `REMEMBERME` |
| Duración | 604800 s (7 días) |
| Path | `/` |

Al cerrar sesión (`GET /admin/logout`):

1. Symfony `RememberMeListener` elimina la cookie remember-me.
2. `logout.delete_cookies` refuerza la eliminación de `REMEMBERME`.
3. `invalidate_session: true` destruye la sesión PHP.

`UserChecker::checkPreAuth` se ejecuta también cuando el usuario entra por remember-me, por lo que un bloqueo temporal o estado inválido impide reautenticación automática.

---

## Rutas y control de acceso

| Ruta | Nombre | Acceso |
| ---- | ------ | ------ |
| `/admin/login` | `app_admin_login` | Público |
| `/admin/logout` | `app_admin_logout` | Público (requiere sesión para tener efecto) |
| `/admin/**` | Varias | `ROLE_ADMIN` |

Usuarios no autenticados que acceden a `/admin/*` son redirigidos a `/admin/login`.

---

## Mensajes de error (español)

Archivo: `translations/security.es.yaml`

| Clave Symfony | Mensaje mostrado |
| ------------- | ---------------- |
| `Invalid credentials.` | Credenciales inválidas. |
| `Invalid CSRF token.` | Ha ocurrido un error, intente nuevamente. |
| Throttling | Demasiados intentos de inicio de sesión. Inténtalo de nuevo en X minutos. |

Mensajes de negocio (no en ese archivo, definidos en código):

| Situación | Mensaje |
| --------- | ------- |
| Bloqueo temporal | Has superado el número de intentos permitidos. Debes esperar X hora(s)... |
| Cuenta no confirmada | Debes confirmar tu cuenta antes de iniciar sesión. |
| Cuenta baneada/inactiva | Mensajes en `UserStatus::loginDeniedMessage()` |
| Rol `user` en backend | No tienes acceso al panel de administración. |

---

## Usuario inicial de desarrollo

Comando: `app:create-initial-data`

```bash
docker compose exec php php bin/console app:create-initial-data
```

Variables de entorno opcionales:

- `INITIAL_USER_EMAIL` — se usa como email **y** nickname
- `INITIAL_USER_PASSWORD` — contraseña del usuario developer inicial

El comando es idempotente: no duplica usuarios existentes con el mismo nickname o email.

---

## Tests relacionados

| Archivo | Qué verifica |
| ------- | ------------ |
| `tests/Admin/Controller/SecurityControllerTest.php` | Login, estados, remember me, logout, nickname case-insensitive |
| `tests/Admin/Security/UserCheckerTest.php` | Bloqueos pre/post auth |
| `tests/Admin/EventSubscriber/AdminSecuritySubscriberTest.php` | Reglas de incremento de intentos |
| `tests/Entity/UserTest.php` | Bloqueo al 4.º intento, reset, normalización |
| `tests/Admin/Support/AdminAuthenticatedClientTrait.php` | Helper para tests funcionales del admin |

Ejecutar:

```bash
docker compose exec php php bin/phpunit tests/Admin/Controller/SecurityControllerTest.php
docker compose exec php php bin/phpunit tests/Admin/Security/
docker compose exec php php bin/phpunit tests/Admin/EventSubscriber/AdminSecuritySubscriberTest.php
```

---

## Extender la autenticación

### Añadir un nuevo estado de cuenta

1. Agregar case en `UserStatus`.
2. Definir `allowsBackendLogin()` y `loginDeniedMessage()`.
3. Ajustar `UserChecker` si el estado requiere lógica especial en `checkPostAuth`.
4. Actualizar tests en `UserCheckerTest` y `SecurityControllerTest`.

### Añadir login en el sitio público (`src/Web`)

El rol `user` está pensado para el frontend público. Implicaciones:

- Crear un firewall o rutas `/login` separadas bajo `src/Web`
- Reutilizar entidad `User`, pero probablemente con `access_control` distinto
- `AdminSecuritySubscriber` solo aplica redirects del backend; no mezclar lógica web allí

### Cambiar umbrales de bloqueo

Modificar constantes en `User`:

```php
public const int MAX_FAILED_LOGIN_ATTEMPTS = 4;
public const int LOGIN_LOCK_HOURS = 3;
```

Actualizar `tests/Entity/UserTest.php` si cambian los valores.

---

## Referencias

- [Symfony Security: Form Login](https://symfony.com/doc/current/security.html#form-login)
- [Symfony Security: Login Throttling](https://symfony.com/doc/current/security/login_throttling.html)
- [Symfony 8: expose_security_errors](https://symfony.com/doc/current/security.html#customize-authentication-error-messages)
- Configuración del proyecto: `config/packages/security.yaml`
