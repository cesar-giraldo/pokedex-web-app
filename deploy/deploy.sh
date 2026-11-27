#!/usr/bin/env bash
# Despliega el checkout actual en cada cliente de /srv/pokedex/clients.
# Construye la imagen una vez. No ejecuta "docker compose down -v":
# ese flag borra el volumen de Postgres.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CLIENTS_DIR="${CLIENTS_DIR:-/srv/pokedex/clients}"
COMPOSE_FILE="${ROOT}/compose.prod.yaml"
IMAGE_NAME="${IMAGE_NAME:-pokedex-app}"

if [[ ! -d "${CLIENTS_DIR}" ]]; then
    echo "No existe ${CLIENTS_DIR}." >&2
    exit 1
fi

shopt -s nullglob
clients=()
for dir in "${CLIENTS_DIR}"/*/; do
    if [[ -f "${dir}.env" ]]; then
        clients+=("${dir}")
    fi
done

if [[ ${#clients[@]} -eq 0 ]]; then
    echo "No hay clientes con .env en ${CLIENTS_DIR}." >&2
    exit 1
fi

for dir in "${clients[@]}"; do
    slug="$(basename "${dir}")"
    engine="$(awk -F= '/^DATABASE_ENGINE=/{print $2; exit}' "${dir}.env")"
    alias_name="$(awk -F= '/^CLIENT_ALIAS=/{print $2; exit}' "${dir}.env")"
    if [[ "${engine}" != "postgresql" ]]; then
        echo "${slug}: DATABASE_ENGINE tiene que ser postgresql." >&2
        exit 1
    fi
    if [[ -z "${alias_name}" ]]; then
        echo "${slug}: falta CLIENT_ALIAS en .env." >&2
        exit 1
    fi
done

if ! docker network inspect edge >/dev/null 2>&1; then
    docker network create edge
fi

tag="$(git -C "${ROOT}" rev-parse --short HEAD)"
echo "Construyendo ${IMAGE_NAME}:${tag}"
docker build \
    --file "${ROOT}/Dockerfile" \
    --target prod \
    --tag "${IMAGE_NAME}:${tag}" \
    --tag "${IMAGE_NAME}:latest" \
    "${ROOT}"

export APP_IMAGE_TAG="${tag}"

for dir in "${clients[@]}"; do
    slug="$(basename "${dir}")"
    echo "Desplegando ${slug}"
    mkdir -p "${dir}/data/postgresql"

    compose=(docker compose --project-directory "${dir}" --file "${COMPOSE_FILE}")

    "${compose[@]}" up -d --wait database-postgresql
    "${compose[@]}" run --rm -T --no-deps php php bin/console doctrine:migrations:migrate --no-interaction --env=prod
    "${compose[@]}" run --rm -T --no-deps php php bin/console cache:warmup --env=prod
    "${compose[@]}" up -d --force-recreate --no-deps php messenger
done

echo "Despliegue listo (${IMAGE_NAME}:${tag})."
