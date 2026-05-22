# 05 · PHP CS Fixer con regla `@Symfony`

**PHP CS Fixer** es la herramienta estándar para mantener tu código PHP limpio y consistente. Aplicaremos la regla oficial `@Symfony`, que es la que usa el propio proyecto Symfony.

> **No es un linter, es un fixer.** No solo te dice qué está mal: lo arregla automáticamente. Espacios, indentación, orden de los `use`, comillas, llaves, sintaxis moderna, etc.

---

## Paso 5.1 · Instalar PHP CS Fixer como dependencia de desarrollo

🐳 Dentro del contenedor:

```bash
docker compose exec php composer require --dev friendsofphp/php-cs-fixer
```

> **`--dev`** lo añade a `require-dev`, así no se instala en producción.

## Paso 5.2 · Crear el archivo de configuración

Crea el archivo `.php-cs-fixer.dist.php` en la raíz del proyecto con este contenido:

```php
<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/config',
        __DIR__.'/public',
    ])
    ->name('*.php')
    ->notPath(['bundles', 'reference'])
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP84Migration' => true,
        'declare_strict_types' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
        'phpdoc_to_comment' => false,
        'concat_space' => ['spacing' => 'one'],
        'array_indentation' => true,
        'method_chaining_indentation' => true,
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__.'/var/.php-cs-fixer.cache')
;
```

### ¿Qué hace cada regla?

| Regla | Efecto |
| ----- | ------ |
| `@Symfony` | Aplica las ~150 reglas del estándar oficial Symfony (extiende `@PER-CS`, que a su vez extiende `@PSR12`). |
| `@Symfony:risky` | Reglas que cambian semántica del código (más estricto). Útil pero hay que probar. |
| `@PHP84Migration` | Moderniza sintaxis a PHP 8.4 (constructor promotion, readonly, etc.). |
| `declare_strict_types` | Añade `declare(strict_types=1);` al inicio de cada archivo. |
| `global_namespace_import` | Importa clases globales (`use \DateTime;`) en vez de prefijar con `\`. |
| `ordered_imports` | Ordena alfabéticamente los `use`. |

> **¿Por qué `.php-cs-fixer.dist.php` y no `.php-cs-fixer.php`?** Por convención, el primero se commitea y el segundo es para overrides locales (está en el `.gitignore` por defecto).

## Paso 5.3 · Ejecutar PHP CS Fixer en modo "dry run"

Antes de modificar archivos, ve qué cambiaría:

🐳 Ejecuta:

```bash
docker compose exec php vendor/bin/php-cs-fixer fix --dry-run --diff
```

- `--dry-run`: no modifica nada, solo lista los archivos que cambiaría.
- `--diff`: muestra exactamente qué líneas cambiaría.

## Paso 5.4 · Aplicar los arreglos

Cuando estés conforme, aplica los cambios:

🐳

```bash
docker compose exec php vendor/bin/php-cs-fixer fix
```

Verás un resumen como:

```text
Loaded config default from "/app/.php-cs-fixer.dist.php".
   1) src/Controller/HomeController.php (no_unused_imports, ordered_imports)
   2) src/Entity/Producto.php (declare_strict_types)

Fixed all files in 0.123 seconds, 12.000 MB memory used
```

## Paso 5.5 · Atajos útiles

Añade scripts a tu `composer.json` para facilitar la ejecución. Edita `composer.json` y agrega esta sección si no existe:

```json
{
    "scripts": {
        "cs:check": "php-cs-fixer fix --dry-run --diff",
        "cs:fix": "php-cs-fixer fix"
    }
}
```

Ahora puedes ejecutar:

```bash
docker compose exec php composer cs:check
docker compose exec php composer cs:fix
```

## Paso 5.6 · Integración con tu editor

### Visual Studio Code / Cursor

Instala la extensión **PHP CS Fixer** (`junstyle.php-cs-fixer`) y añade a tu `.vscode/settings.json`:

```json
{
    "php-cs-fixer.executablePath": "${workspaceFolder}/vendor/bin/php-cs-fixer",
    "php-cs-fixer.config": ".php-cs-fixer.dist.php",
    "php-cs-fixer.onsave": true,
    "php-cs-fixer.formatHtml": false
}
```

> **Nota:** Esto usa el binario instalado en `vendor/bin`, que depende de tener `vendor/` montado en tu máquina (lo está, gracias al volumen `./:/app`).

### PhpStorm

`Settings → PHP → Quality Tools → PHP CS Fixer` → marca **Configuration file** y selecciona `.php-cs-fixer.dist.php`. Luego en `Editor → Inspections → PHP → Quality Tools → PHP CS Fixer validation`, marca la casilla.

## Paso 5.7 · Hook de Git (Recomendado)

Para ejecutar el fixer u otros comandos automáticamente antes de cada commit, podemos usar la forma manual de configurar los hooks de git (sin necesidad de instalar nuevas dependencias), siga los siguientes pasos:


* Localiza la carpeta: Ve al directorio oculto `.git/hooks` de tu proyecto.

* Crea el script: Encontrarás archivos terminados en .sample. Ignóralos o crea un archivo nuevo sin extensión (por ejemplo, para que ejecute php-cs-fixer o tus tests de PHPUnit antes de cada commit, crea un archivo llamado `pre-commit`).

```bash
touch pre-commit
```

* Edita el archivo: Añade las instrucciones de bash que desees ejecutar:

```bash
#!/bin/bash

echo "Instanciando validaciones de código..."

# 1. Validar estilo de código con PHP-CS-Fixer

echo "Comprobando formato de código con PHP-CS-Fixer..."
./vendor/bin/php-cs-fixer fix --dry-run --diff

if [ $? -ne 0 ]; then
    echo "❌ ¡Error de estilo! Ejecuta './vendor/bin/php-cs-fixer fix' para corregirlo automáticamente."
    exit 1
fi


# 2. Ejecutar pruebas unitarias

echo "Ejecutando pruebas antes del commit..."

# Ejecucion de pruebas en maquina local
#./vendor/bin/phpunit

# Ejecucion de pruebas mediante docker
docker compose exec php php bin/phpunit

if [ $? -ne 0 ]; then
    echo "¡Las pruebas fallaron! El commit ha sido cancelado."
    exit 1
fi

echo "✅ ¡Todo correcto! Procediendo con el commit..."
exit 0
```

* Dar permisos de ejecución: Debes hacer que el archivo sea ejecutable para que Git lo reconozca:

```bash
chmod +x .git/hooks/pre-commit
```

* Listo, al tratar de hacer tu proximo commit se ejecutaran tus pruebas unitarias y el verificador de php cs fixer.


## Paso 5.8 · Integración con CI (GitHub Actions) - Opcional

Crea `.github/workflows/cs.yml`:

```yaml
name: Code Style

on:
  pull_request:
    branches: [main, develop]

jobs:
  php-cs-fixer:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: none

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Check code style
        run: vendor/bin/php-cs-fixer fix --dry-run --diff
```

Ahora cualquier PR que rompa el estilo fallará el check antes de poder mergearse.


## Paso 5.9 · Bonus: PHPStan para análisis estático profundo - Recomendado

PHP CS Fixer arregla el **estilo**. Para errores de tipos, dead code y bugs reales, añade **PHPStan**:

```bash
docker compose exec php composer require --dev phpstan/phpstan phpstan/extension-installer phpstan/phpstan-symfony
```

(El paquete extension-installer se encarga de configurar automáticamente la extensión de Symfony dentro de PHPStan).

Crea el archivo de configuración (phpstan.neon) `phpstan.dist.neon`:

```yaml
parameters:
    level: 8
    paths:
        - src
        - tests
    symfony:
        # Ruta al contenedor de desarrollo de Symfony 8
        container_xml_path: var/cache/dev/App_KernelDevDebugContainer.xml
```

Ejecútalo:

```bash
docker compose exec php vendor/bin/phpstan analyse
```

> Nivel 0 = básico, nivel 9 = máximo. Empieza en 5 si tu proyecto ya tiene código y sube progresivamente.


Integrarlo en tu Git Hook (pre-commit).
Añade el análisis estático a tu script actual de Git Hooks. Actualiza tu archivo .git/hooks/pre-commit para incluirlo en la cadena de validación:

```bash
# 3. Análisis estático profundo con PHPStan
echo "Ejecutando análisis estático con PHPStan..."
./vendor/bin/phpstan analyse
if [ $? -ne 0 ]; then
    echo "❌ ¡PHPStan detectó errores de lógica o tipado en el código!"
    exit 1
fi
```

### Estrategia recomendada para Symfony 8:
Empieza con un nivel bajo: PHPStan tiene niveles del 0 al 9. Si es un proyecto que ya tiene meses de desarrollo, empezar en el nivel 5 o 6 podría darte cientos de errores. Inicia en el nivel 1 o 2, limpia los errores, y ve subiendo paulatinamente la exigencia en tu archivo phpstan.neon.

---

✅ Tu código está siempre limpio y consistente. Solo falta saber los comandos del día a día.

➡️ Continúa en [`06-comandos-y-troubleshooting.md`](./06-comandos-y-troubleshooting.md).
