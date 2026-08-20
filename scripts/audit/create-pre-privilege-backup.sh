#!/usr/bin/env bash
set -Eeuo pipefail

expected_database="digital_library_recovered"
backup_root="/app/storage/app/backups/security-pre-privilege"

if [[ "${DB_DATABASE:-}" != "${expected_database}" ]]; then
    echo "Refusing backup: production database identity mismatch." >&2
    exit 64
fi
if [[ -z "${DB_USERNAME:-}" || -z "${DB_PASSWORD:-}" ]]; then
    echo "Refusing backup: database connection environment is incomplete." >&2
    exit 64
fi

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
if [[ ! "${timestamp}" =~ ^[0-9]{8}T[0-9]{6}Z$ ]]; then
    echo "Refusing backup: invalid timestamp." >&2
    exit 64
fi

destination="${backup_root}/${timestamp}"
dump_path="${destination}/${expected_database}_${timestamp}.dump"
toc_path="${dump_path}.toc"
sha_path="${dump_path}.sha256"

umask 077
install -d -m 700 "${destination}"
for artifact in "${dump_path}" "${toc_path}" "${sha_path}"; do
    if [[ -e "${artifact}" ]]; then
        echo "Refusing backup: artifact already exists." >&2
        exit 73
    fi
done

export PGPASSWORD="${DB_PASSWORD}"
pg_dump \
    --host="${DB_HOST:-postgres}" \
    --port="${DB_PORT:-5432}" \
    --username="${DB_USERNAME}" \
    --dbname="${expected_database}" \
    --format=custom \
    --file="${dump_path}"
pg_restore --list "${dump_path}" > "${toc_path}"
sha256sum "${dump_path}" > "${sha_path}"

chmod 600 "${dump_path}" "${toc_path}" "${sha_path}"
echo "backup=${dump_path}"
echo "size=$(stat -c %s "${dump_path}")"
echo "sha256=$(cut -d' ' -f1 "${sha_path}")"
echo "toc=PASS"
