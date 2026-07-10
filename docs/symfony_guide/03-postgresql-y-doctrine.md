# 03 · PostgreSQL 18 + Doctrine ORM

> **Relación con este repositorio:** Este capítulo enseña Doctrine creando una entidad de ejemplo `Producto`. La aplicación Pokédex del repo usa `Pokemon` y `PokemonType` en su lugar. Los conceptos (entidad, migración, repositorio, controlador) son los mismos. Ver [`07-aplicacion-pokedex.md`](./07-aplicacion-pokedex.md) para el dominio real.

Doctrine ya viene incluido en `webapp-pack`. En esta sección aprenderás a:

1. Configurar la conexión a PostgreSQL 18.
2. Crear la base de datos.
3. Crear tu primera entidad y migración.
4. Conectarte a la base de datos desde un cliente externo (DBeaver, TablePlus, etc.).

---

## Paso 3.1 · Configurar la URL de conexión en `.env`

Cuando instalaste `webapp-pack`, la recipe de Doctrine añadió una línea por defecto en tu archivo `.env`. Ábrelo y asegúrate de que el bloque de Doctrine apunte al servicio Docker de Postgres:

```dotenv
###> doctrine/doctrine-bundle ###
DATABASE_URL="postgresql://app:app@database:5432/app?serverVersion=18&charset=utf8"
###< doctrine/doctrine-bundle ###
```

> Si por defecto la recipe puso una URL distinta (por ejemplo apuntando a `127.0.0.1` o a SQLite), cámbiala por la de arriba.

Esta URL le dice a Doctrine:

| Parte                    | Significado                                         |
| ------------------------ | --------------------------------------------------- |
| `postgresql://`          | Driver de PostgreSQL                                |
| `app:app`                | Usuario y contraseña                                |
| `@database`              | Hostname → es el nombre del **servicio** de Compose |
| `:5432`                  | Puerto estándar de PostgreSQL                       |
| `/app`                   | Nombre de la base de datos                          |
| `?serverVersion=18`      | Versión del servidor (importante para Doctrine)     |
| `&charset=utf8`          | Codificación                                        |

> **Atención:** Dentro del contenedor PHP, el host de la BD es `database` (no `localhost`). Desde tu máquina sería `localhost:5432`.

### ⚠️ Por qué `DATABASE_URL` va en `.env` y NO en `compose.yaml`

Symfony tiene un orden de precedencia para leer variables. **Las variables que vienen del entorno real ganan SIEMPRE** sobre las que están en archivos `.env.*`:

```text
Variables del entorno real (compose.yaml environment, export, etc.)  ← MÁS PRIORIDAD ⭐
   ↓
.env.local.php (caché compilada)
   ↓
.env.local
   ↓
.env.{env}.local  (ej. .env.dev.local)
   ↓
.env.{env}        (ej. .env.dev)
   ↓
.env                                                                 ← MENOS PRIORIDAD
```

Si pusiéramos `DATABASE_URL` en el bloque `environment:` del `compose.yaml`, esa URL **siempre ganaría**, y editar `.env.local` no tendría ningún efecto. Por eso la guía la deja **fuera** de `compose.yaml`: así puedes sobrescribirla cómodamente en `.env.local` para tu uso personal.

### Tu archivo personal: `.env.local`

`.env.local` está en el `.gitignore`, no se sube al repo y es **el lugar correcto para cambios personales**. Por ejemplo, si quieres apuntar a otra base de datos:

```dotenv
DATABASE_URL="postgresql://miusuario:micontra@database:5432/mi_otra_db?serverVersion=18&charset=utf8"
```

### Verificar de dónde se está cargando cada variable

```bash
docker compose exec php php bin/console debug:dotenv
```

La columna **File** te dice de qué archivo se leyó cada variable. Si ves `(real env)` para `DATABASE_URL`, significa que algo del entorno (compose.yaml, export, etc.) la está sobrescribiendo y tu `.env.local` se está ignorando.

## Paso 3.2 · Comprobar que Doctrine ve la base de datos

🐳 Ejecuta:

```bash
docker compose exec php php bin/console doctrine:database:create --if-not-exists
```

Salida esperada:

```text
Database "app" for connection named default already exists. Skipped.
```

> Es normal que ya exista, porque la imagen `postgres` la crea automáticamente al arrancar (gracias a `POSTGRES_DB: app`).

Verifica que Doctrine puede conectarse:

```bash
docker compose exec php php bin/console dbal:run-sql "SELECT version()"
```

Deberías ver algo como `PostgreSQL 18.3 ...`.

> **Nota sobre nombres de comandos:** En `doctrine/doctrine-bundle` 2.13+ (lo que viene con Symfony 8) el comando `doctrine:query:sql` fue **renombrado a `dbal:run-sql`**. Si en algún tutorial viejo ves `doctrine:query:sql`, su reemplazo actual es `dbal:run-sql`. El comando `doctrine:query:dql` (para queries del ORM) sí sigue existiendo. Para listar lo que tienes disponible: `docker compose exec php php bin/console list dbal`.

## Paso 3.3 · Crear tu primera entidad

Las **entidades** son clases PHP que representan tablas en la base de datos. Vamos a crear una entidad `Producto` como ejemplo.

🐳 Ejecuta:

```bash
docker compose exec php php bin/console make:entity Producto
```

El comando es interactivo. Responde así:

```text
New property name (press <return> to stop adding fields): nombre
Field type [string]: <ENTER>
Field length [255]: <ENTER>
Can this field be null in the database (nullable)? (yes/no) [no]: <ENTER>

New property name: precio
Field type [string]: decimal
Precision: 10
Scale: 2
nullable? [no]: <ENTER>

New property name: descripcion
Field type [string]: text
nullable? [no]: yes

New property name: <ENTER>  (para terminar)
```

Esto genera dos archivos:

- `src/Entity/Producto.php` — la clase de la entidad.
- `src/Repository/ProductoRepository.php` — para consultar productos.

## Paso 3.4 · Crear la migración

Las **migraciones** son scripts que llevan la base de datos del estado actual al estado deseado. Doctrine las genera automáticamente comparando tus entidades con las tablas existentes.

🐳 Genera la migración:

```bash
docker compose exec php php bin/console make:migration
```

Esto crea un archivo en `migrations/`, por ejemplo `Version20260507000000.php`. Ábrelo y revisa el SQL: debería contener un `CREATE TABLE producto (...)`.

🐳 Ejecuta la migración:

```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

¡Listo! La tabla `producto` ya existe en PostgreSQL.

## Paso 3.5 · Verificar la tabla

Conéctate al contenedor de Postgres y mira las tablas:

```bash
docker compose exec database psql -U app -d app -c "\dt"
```

Salida esperada:

```text
            List of relations
 Schema |        Name        | Type  | Owner
--------+--------------------+-------+-------
 public | doctrine_migration_versions | table | app
 public | producto                    | table | app
```

> La tabla `doctrine_migration_versions` registra qué migraciones se han aplicado, para no ejecutarlas dos veces.

## Paso 3.6 · Insertar y consultar datos desde un controlador

Edita `src/Controller/HomeController.php`:

```php
<?php

namespace App\Controller;

use App\Entity\Producto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $em): Response
    {
        // Insertar un producto si no existe ninguno
        $repo = $em->getRepository(Producto::class);
        if (0 === $repo->count([])) {
            $producto = (new Producto())
                ->setNombre('Camiseta')
                ->setPrecio('29.99')
                ->setDescripcion('Camiseta de algodón');
            $em->persist($producto);
            $em->flush();
        }

        return $this->render('home/index.html.twig', [
            'productos' => $repo->findAll(),
        ]);
    }
}
```

Y la plantilla `templates/home/index.html.twig`:

```twig
{% extends 'base.html.twig' %}

{% block title %}Productos{% endblock %}

{% block body %}
    <h1>Listado de productos</h1>
    <ul>
    {% for producto in productos %}
        <li>{{ producto.nombre }} — ${{ producto.precio }}</li>
    {% else %}
        <li>No hay productos.</li>
    {% endfor %}
    </ul>
{% endblock %}
```

Recarga <https://localhost> y verás la lista.

## Paso 3.7 · Conectarte desde un cliente SQL externo (opcional)

Si quieres usar **DBeaver**, **TablePlus**, **pgAdmin** o el `psql` de tu máquina, conecta con:

| Campo       | Valor       |
| ----------- | ----------- |
| Host        | `localhost` |
| Puerto      | `5432`      |
| Base de datos | `app`     |
| Usuario     | `app`       |
| Contraseña  | `app`       |

> Esto funciona porque en `compose.yaml` mapeamos `5432:5432`.

## Paso 3.8 · Comandos útiles de Doctrine

```bash
# Ver el SQL que generaría una migración (sin ejecutarla)
docker compose exec php php bin/console doctrine:schema:update --dump-sql

# Validar el mapeo de tus entidades
docker compose exec php php bin/console doctrine:schema:validate

# Borrar y recrear la BD (¡destructivo, solo en dev!)
docker compose exec php php bin/console doctrine:database:drop --force
docker compose exec php php bin/console doctrine:database:create
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# Listar migraciones aplicadas / pendientes
docker compose exec php php bin/console doctrine:migrations:status
```

## Paso 3.9 · Cargar datos de prueba (fixtures)

Para datos de ejemplo durante el desarrollo, instala el bundle de fixtures:

```bash
docker compose exec php composer require --dev orm-fixtures
```

Luego puedes generar fixtures con:

```bash
docker compose exec php php bin/console make:fixtures
```

Y cargarlas:

```bash
docker compose exec php php bin/console doctrine:fixtures:load
```

---

✅ Tu base de datos está conectada y migraciones funcionando. Ahora vamos a darle estilo con Tailwind 4 e interactividad con Stimulus.

➡️ Continúa en [`04-tailwind-y-stimulus.md`](./04-tailwind-y-stimulus.md).
