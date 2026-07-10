# Documentación del proyecto

Este directorio contiene la documentación interna del **Pokedex Web App**.

## ¿Qué leer según tu objetivo?

| Objetivo | Documento |
| -------- | --------- |
| Arrancar el proyecto ya clonado | [README.md](../README.md) en la raíz |
| Entender la app Pokédex (entidades, PokeAPI, rutas, UI kit) | [symfony_guide/07-aplicacion-pokedex.md](./symfony_guide/07-aplicacion-pokedex.md) |
| Aprender a construir el stack desde cero | [symfony_guide/README.md](./symfony_guide/README.md) (pasos 00–06) |
| Comandos Docker, Composer, Doctrine, troubleshooting | [symfony_guide/06-comandos-y-troubleshooting.md](./symfony_guide/06-comandos-y-troubleshooting.md) |

## Estructura de `docs/`

```
docs/
└── symfony_guide/
    ├── README.md                      # Índice de la guía paso a paso
    ├── 00-introduccion.md             # Stack y requisitos
    ├── 01-estructura-y-docker.md      # Dockerfile + compose.yaml
    ├── 02-instalar-symfony.md         # Symfony 8 + webapp-pack
    ├── 03-postgresql-y-doctrine.md    # Doctrine (ejemplo tutorial: Producto)
    ├── 04-tailwind-y-stimulus.md      # Tailwind 4 + Stimulus + UX
    ├── 05-php-cs-fixer.md             # PHP CS Fixer + PHPStan
    ├── 06-comandos-y-troubleshooting.md
    └── 07-aplicacion-pokedex.md       # App real: Pokemon, PokeAPI, /design
```

## Relación entre la guía y el código actual

Los pasos **01–06** son un **tutorial genérico**: enseñan el stack usando una entidad de ejemplo llamada `Producto`. El repositorio actual evolucionó hacia una **aplicación Pokédex** con:

- Entidades `Pokemon` y `PokemonType`
- Cliente HTTP `App\Service\PokeAPI\PokeAPIClient`
- Comando `search-store-pokemons`
- Live Components y kit de diseño en `/design`

El paso **07** documenta esa capa de aplicación y cómo se relaciona con lo aprendido en los pasos anteriores.

## Convenciones

- Comandos con prefijo `docker compose exec php` se ejecutan **dentro del contenedor**.
- La URL local por defecto es <https://localhost> (certificado autofirmado de FrankenPHP).
- Variables de entorno: ver sección *Environment variables* en el [README](../README.md).
