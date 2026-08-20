#!/usr/bin/env bash
set -Eeuo pipefail

# Creates a PostgreSQL custom-format backup, verifies its TOC, restores it into
# a uniquely named *_test database, and checks the core dataset before cleanup.
# It never accepts a test database as the backup source and never overwrites an
# existing artifact.

database="${1:-${DB_DATABASE:-}}"
postgres_container="${POSTGRES_CONTAINER:-library-postgres-1}"
postgres_user="${POSTGRES_USER:-library_user}"
backup_root="${LIBRARY_BACKUP_ROOT:-$(pwd)/storage/app/backups/verified}"
offsite_root="${LIBRARY_BACKUP_OFFSITE_ROOT:-}"
retention_days="${LIBRARY_BACKUP_RETENTION_DAYS:-30}"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"

if [[ ! "${database}" =~ ^[A-Za-z0-9_]+$ ]] || [[ "${database}" == *_test ]]; then
    echo "Refusing backup: source DB must be a non-test PostgreSQL identifier." >&2
    exit 64
fi
if [[ ! "${retention_days}" =~ ^[0-9]+$ ]] || (( retention_days < 7 )); then
    echo "Refusing backup: retention must be an integer of at least 7 days." >&2
    exit 64
fi
if ! docker inspect "${postgres_container}" >/dev/null 2>&1; then
    echo "Refusing backup: PostgreSQL container is unavailable." >&2
    exit 69
fi

destination="${backup_root}/${database}/${timestamp}"
dump_path="${destination}/${database}_${timestamp}.dump"
toc_path="${dump_path}.toc"
sha_path="${dump_path}.sha256"
verify_path="${dump_path}.verify.txt"
verify_database="${database}_backup_${timestamp//[^0-9]/}_test"
verify_database="${verify_database:0:63}"

install -d -m 700 "${destination}"
for artifact in "${dump_path}" "${toc_path}" "${sha_path}" "${verify_path}"; do
    if [[ -e "${artifact}" ]]; then
        echo "Refusing backup: artifact already exists: ${artifact}" >&2
        exit 73
    fi
done

cleanup_verify_database() {
    docker exec "${postgres_container}" dropdb --username="${postgres_user}" --if-exists "${verify_database}" >/dev/null 2>&1 || true
}
trap cleanup_verify_database EXIT

umask 077
docker exec "${postgres_container}" pg_dump --username="${postgres_user}" --dbname="${database}" --format=custom > "${dump_path}"
docker run --rm --network none --read-only -v "${destination}:/backup:ro" postgres:18 \
    pg_restore --list "/backup/$(basename "${dump_path}")" > "${toc_path}"
sha256sum "${dump_path}" > "${sha_path}"

docker exec "${postgres_container}" createdb --username="${postgres_user}" --encoding=UTF8 "${verify_database}"
docker exec -i "${postgres_container}" pg_restore --username="${postgres_user}" --dbname="${verify_database}" --exit-on-error --no-owner < "${dump_path}"
docker exec "${postgres_container}" psql --username="${postgres_user}" --dbname="${verify_database}" --no-psqlrc --tuples-only --no-align --set=ON_ERROR_STOP=1 \
    --command="SELECT 'database=' || current_database();
               SELECT 'bibliographic_records=' || count(*) FROM bibliographic_records;
               SELECT 'book_copies=' || count(*) FROM book_copies;
               SELECT 'users=' || count(*) FROM users;
               SELECT 'migrations=' || count(*) FROM migrations;
               SELECT 'unvalidated_foreign_keys=' || count(*) FROM pg_constraint WHERE contype='f' AND NOT convalidated;" \
    > "${verify_path}"

if ! grep -qx 'unvalidated_foreign_keys=0' "${verify_path}"; then
    echo "Backup restore verification failed FK validation." >&2
    exit 65
fi

if [[ -n "${offsite_root}" ]]; then
    offsite_destination="${offsite_root%/}/${database}/${timestamp}"
    install -d -m 700 "${offsite_destination}"
    cp --no-clobber "${dump_path}" "${toc_path}" "${sha_path}" "${verify_path}" "${offsite_destination}/"
    sha256sum --check "${offsite_destination}/$(basename "${sha_path}")" >/dev/null
    echo "offsite=${offsite_destination}"
else
    echo "offsite=NOT_CONFIGURED" >&2
fi

echo "backup=${dump_path}"
echo "sha256=$(cut -d' ' -f1 "${sha_path}")"
echo "restore_test=${verify_database}:PASS"
echo "retention_days=${retention_days} (policy only; this command does not delete forensic artifacts)"
