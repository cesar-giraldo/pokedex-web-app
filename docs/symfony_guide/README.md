# Guía: Symfony 8 + Tailwind 4 + Stimulus + PostgreSQL 18 + Docker

> Guía paso a paso para construir desde cero una aplicación web PHP moderna usando Symfony 8, Twig, Tailwind CSS 4, StimulusBundle, PostgreSQL 18 y Docker. Pensada para principiantes.

## Índice

| Paso | Archivo                                                           | Contenido                                              |
| ---- | ----------------------------------------------------------------- | ------------------------------------------------------ |
| 0    | [`00-introduccion.md`](./00-introduccion.md)                      | Visión general del stack y requisitos previos          |
| 1    | [`01-estructura-y-docker.md`](./01-estructura-y-docker.md)        | Crear `Dockerfile` y `compose.yaml` con FrankenPHP     |
| 2    | [`02-instalar-symfony.md`](./02-instalar-symfony.md)              | Instalar Symfony 8, Twig y AssetMapper                 |
| 3    | [`03-postgresql-y-doctrine.md`](./03-postgresql-y-doctrine.md)    | Conectar PostgreSQL 18 con Doctrine ORM                |
| 4    | [`04-tailwind-y-stimulus.md`](./04-tailwind-y-stimulus.md)        | Instalar Tailwind 4 y StimulusBundle                   |
| 5    | [`05-php-cs-fixer.md`](./05-php-cs-fixer.md)                      | Configurar PHP CS Fixer con la regla `@Symfony`        |
| 6    | [`06-comandos-y-troubleshooting.md`](./06-comandos-y-troubleshooting.md) | Comandos del día a día y solución de problemas |
| 7    | [`07-aplicacion-pokedex.md`](./07-aplicacion-pokedex.md)                 | App real del repo: Pokemon, PokeAPI, `/design` |

## Lectura recomendada

Si nunca has tocado Symfony o Docker, lee los archivos en orden. Si ya conoces el stack, puedes ir directo al paso que te interese.

Si clonaste este repositorio y solo quieres entender **cómo funciona la Pokédex**, lee directamente el paso 07 y el [README](../../README.md) de la raíz.

## Cómo arrancar el proyecto resultante

Después de completar la guía, cualquier desarrollador del equipo podrá arrancar el proyecto con:

```bash
git clone <tu-repositorio>
cd <tu-repositorio>
docker compose up -d
docker compose exec php composer install
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console search-store-pokemons 10 --write=true   # opcional
```

Y abrir <https://localhost> (Pokédex) o <https://localhost/design> (UI kit) en el navegador.

## Stack final

- **PHP 8.5** + **FrankenPHP 1.x** (servidor web + intérprete en un proceso)
- **Symfony 8.0.x** con `webapp-pack`
- **Twig 3.x** (motor de plantillas)
- **AssetMapper** (gestión de assets sin Webpack)
- **Tailwind CSS 4.x** vía `symfonycasts/tailwind-bundle`
- **StimulusBundle 3.x** (`@hotwired/stimulus`) + **Symfony UX 3.x** (Live Component, Turbo, Icons)
- **PostgreSQL 18.3** + **Doctrine ORM 3.x**
- **PHP CS Fixer** con regla `@Symfony`
- **Docker Compose v2** orquestando todo
