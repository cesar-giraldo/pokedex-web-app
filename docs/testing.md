# Pruebas automatizadas

Este documento describe cómo están organizadas las pruebas del proyecto y cómo ejecutar cada capa por separado.

## Tipos de prueba

El proyecto usa **tres suites** de PHPUnit, alineadas con las convenciones de Symfony 8:

| Suite | Grupo PHPUnit | Clase base típica | Infraestructura |
| ----- | ------------- | ----------------- | --------------- |
| **unit** | `unit` | `PHPUnit\Framework\TestCase`, `ConstraintValidatorTestCase` | Ninguna (sin kernel, sin HTTP, sin BD) |
| **integration** | `integration` | `KernelTestCase` | Kernel Symfony, contenedor DI, formularios, repositorios, comandos |
| **functional** | `functional` | `WebTestCase` | Kernel + cliente HTTP (`createClient()`), rutas end-to-end |

### Criterio de ubicación por directorio

| Suite | Directorios / archivos |
| ----- | ---------------------- |
| `unit` | Todo `tests/` excepto los listados abajo |
| `integration` | `tests/Repository/`, `tests/Admin/Form/`, `tests/Admin/Command/` |
| `functional` | `tests/Admin/Controller/`, `tests/Web/Controller/`, `tests/Api/Controller/`, `tests/EventSubscriber/HtmlExceptionPageTest.php` |

Cada clase de prueba lleva además el atributo `#[Group('unit'|'integration'|'functional')]` correspondiente. Esto permite filtrar por grupo si lo necesitas:

```bash
docker compose exec php php bin/phpunit --group=unit
```

> **Nota:** Las suites por directorio y los grupos deben mantenerse sincronizados al crear nuevas pruebas.

## Análisis estático vs pruebas PHPUnit

El análisis de **código estático** (sin ejecutar la aplicación) es independiente de PHPUnit:

| Herramienta | Comando Composer | Qué valida |
| ----------- | ---------------- | ---------- |
| PHPStan | `composer phpstan` | Tipos, lógica estática (nivel 8) |
| PHP-CS-Fixer | `composer cs:check` | Estilo y convenciones PSR |

Ambas se pueden ejecutar juntas con:

```bash
docker compose exec php composer test:static
```

## Comandos (Docker)

Todos los comandos asumen contenedores en ejecución (`docker compose up -d`).

### Análisis estático

```bash
# PHPStan + PHP-CS-Fixer
docker compose exec php composer test:static

# Por separado
docker compose exec php composer phpstan
docker compose exec php composer cs:check
docker compose exec php composer cs:fix    # corrige estilo automáticamente
```

### PHPUnit por suite

```bash
# Todas las suites (unit + integration + functional)
docker compose exec php composer test

# Solo unitarias (~45 clases, sin BD ni HTTP)
docker compose exec php composer test:unit

# Solo integración (kernel + BD)
docker compose exec php composer test:integration

# Solo funcionales (HTTP end-to-end)
docker compose exec php composer test:functional
```

Equivalente directo con `bin/phpunit`:

```bash
docker compose exec php php bin/phpunit --testsuite=unit
docker compose exec php php bin/phpunit --testsuite=integration
docker compose exec php php bin/phpunit --testsuite=functional
```

### Filtrar por grupo

```bash
docker compose exec php php bin/phpunit --group=unit
docker compose exec php php bin/phpunit --group=integration
docker compose exec php php bin/phpunit --group=functional
```

### Ejecutar un archivo o método concreto

```bash
docker compose exec php php bin/phpunit tests/Entity/UserTest.php
docker compose exec php php bin/phpunit --filter testLocksAccountAfterFourthFailedAttempt
```

## CI (GitHub Actions)

El workflow `.github/workflows/ci.yml` ejecuta dos jobs **sin servicios externos** (sin base de datos ni Docker Compose):

| Job | Contenido |
| --- | --------- |
| `static-quality` | `composer phpstan` + `composer cs:check` |
| `unit-tests` | `composer test:unit` |

Las suites `integration` y `functional` **no** se ejecutan en CI por ahora; requieren base de datos y servicios del stack Docker. Se incorporarán en una fase posterior.

## Crear nuevas pruebas

1. Ubica el archivo en el directorio que corresponda a su tipo (ver tabla arriba).
2. Añade el atributo de grupo en la clase:

```php
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
final class MiServicioTest extends TestCase
{
    // ...
}
```

3. Usa la clase base adecuada:
   - Lógica aislada → `TestCase`
   - Validadores → `ConstraintValidatorTestCase`
   - Formularios / repositorios / comandos → `KernelTestCase`
   - Controladores HTTP → `WebTestCase`

## Configuración

- **PHPUnit:** `phpunit.dist.xml` (tres suites + `symfony/phpunit-bridge`)
- **PHPStan:** `phpstan.dist.neon`
- **PHP-CS-Fixer:** `.php-cs-fixer.dist.php`
- **Bootstrap de tests:** `tests/bootstrap.php`
