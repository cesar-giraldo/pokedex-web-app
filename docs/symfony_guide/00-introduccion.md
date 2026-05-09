# Guía paso a paso: Symfony 8 + Tailwind 4 + Stimulus + PostgreSQL 18 + Docker

Bienvenido. Esta guía te llevará desde cero hasta tener una aplicación web Symfony 8 funcionando dentro de Docker, con todo el stack frontend/backend listo para desarrollar. Está pensada para principiantes: cada comando se explica y cada archivo se justifica.

---

## ¿Qué vamos a construir?

Una aplicación web con esta arquitectura:

```text
┌─────────────────────────────────────────────────────────────┐
│                    Tu máquina (host)                        │
│                                                             │
│   ┌────────────────────────┐   ┌─────────────────────────┐  │
│   │   Contenedor PHP/web   │   │  Contenedor PostgreSQL  │  │
│   │                        │   │                         │  │
│   │   FrankenPHP 1.x       │◄──┤  PostgreSQL 18.3        │  │
│   │   PHP 8.4              │   │  Puerto 5432            │  │
│   │   Symfony 8.0.x        │   │                         │  │
│   │   Twig + Tailwind 4    │   └─────────────────────────┘  │
│   │   StimulusBundle       │                                │
│   │   Puertos 80/443       │                                │
│   └────────────────────────┘                                │
│              ▲                                              │
│              │  https://localhost                           │
│              │                                              │
│         Tu navegador                                        │
└─────────────────────────────────────────────────────────────┘
```

## Stack tecnológico (versiones objetivo)

| Componente            | Versión      | Por qué la elegimos                                                |
| --------------------- | ------------ | ------------------------------------------------------------------ |
| **PHP**               | 8.4.x        | Requisito mínimo de Symfony 8                                      |
| **Symfony**           | 8.0.10 (8.0.\*) | Última rama estable                                                |
| **FrankenPHP**        | 1.x          | Servidor web moderno con HTTPS y HTTP/3 incluidos (más simple que Nginx + PHP-FPM) |
| **Twig**              | 3.x          | Motor de plantillas oficial de Symfony                             |
| **AssetMapper**       | (incluido)   | Sistema oficial de Symfony para gestionar assets sin Webpack       |
| **TailwindBundle**    | 0.12+        | Bundle oficial de SymfonyCasts que descarga el binario de Tailwind |
| **Tailwind CSS**      | 4.x          | Última versión, con motor Oxide ultra rápido                       |
| **StimulusBundle**    | 2.x          | Integración oficial de Stimulus + Symfony UX                       |
| **PostgreSQL**        | 18.3         | Última versión estable, con I/O asíncrono                          |
| **Doctrine ORM**      | 3.x          | ORM por defecto en Symfony                                         |
| **PHP CS Fixer**      | 3.x          | Análisis estático y formateo automático con regla `@Symfony`       |
| **Docker / Compose**  | 24+ / v2.10+ | Para correr todo sin instalar nada en tu máquina                   |

> **Nota sobre versiones:** Symfony libera parches cada mes. Cuando ejecutes `composer require symfony/skeleton "8.0.*"`, automáticamente obtendrás la última versión 8.0.x disponible (8.0.10, 8.0.11, etc.). No te preocupes por fijar una versión exacta del parche.

---

## Requisitos previos

Antes de empezar, asegúrate de tener instalado en tu máquina:

1. **Docker Desktop** (Windows, macOS) o **Docker Engine + Docker Compose v2** (Linux).
   - Verifícalo con: `docker --version` y `docker compose version`.
   - Descarga: <https://www.docker.com/products/docker-desktop>
2. **Un editor de código** como Visual Studio Code, PhpStorm o Cursor.
3. **Git**, para versionar tu proyecto.
4. **Una terminal** (Terminal en macOS/Linux, PowerShell o WSL en Windows).

> **No necesitas tener PHP, Composer, Node.js ni PostgreSQL instalados en tu máquina.** Todo correrá dentro de Docker. Esa es la magia.

---

## Cómo está organizada esta guía

La guía está dividida en archivos numerados. Síguelos en orden:

| Archivo                            | Contenido                                                          |
| ---------------------------------- | ------------------------------------------------------------------ |
| `00-introduccion.md`               | Este archivo. Visión general del stack.                            |
| `01-estructura-y-docker.md`        | Crear el proyecto, escribir el `Dockerfile` y el `compose.yaml`.   |
| `02-instalar-symfony.md`           | Instalar Symfony 8 + Twig + AssetMapper dentro del contenedor.     |
| `03-postgresql-y-doctrine.md`      | Conectar Symfony a PostgreSQL 18 y crear la primera entidad.       |
| `04-tailwind-y-stimulus.md`        | Instalar TailwindBundle (Tailwind 4) + StimulusBundle.             |
| `05-php-cs-fixer.md`               | Configurar PHP CS Fixer con la regla `@Symfony`.                   |
| `06-comandos-y-troubleshooting.md` | Comandos del día a día y solución de problemas comunes.            |

---

## Convenciones que usaré

- Los bloques con prefijo `🐳` se ejecutan **dentro del contenedor** (con `docker compose exec php …`).
- Los bloques con prefijo `💻` se ejecutan **en tu máquina** (host).
- Cuando veas `<algo>`, significa que debes reemplazarlo por tu propio valor.

¡Empecemos! Abre el archivo [`01-estructura-y-docker.md`](./01-estructura-y-docker.md).
