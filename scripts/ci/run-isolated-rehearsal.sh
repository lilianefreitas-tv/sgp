#!/usr/bin/env bash

set -Eeuo pipefail

evidence_dir="${REHEARSAL_EVIDENCE_DIR:-storage/app/rehearsal}"
mkdir -p "$evidence_dir"

finish() {
    local exit_code=$?
    local result="REPROVADO"

    if [[ $exit_code -eq 0 ]]; then
        result="APROVADO TECNICAMENTE"
    fi

    {
        printf 'Ensaio isolado PostgreSQL 18\n'
        printf 'Resultado: %s\n' "$result"
        printf 'Código de saída: %s\n' "$exit_code"
        printf 'Commit: %s\n' "$(git rev-parse HEAD 2>/dev/null || printf 'indisponível')"
        printf 'Fim UTC: %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')"
        printf 'Observação: este resultado não equivale à homologação final da BL-SGP-002.\n'
    } > "$evidence_dir/99-resultado.txt"

    : > "$evidence_dir/MANIFESTO-SHA256.txt"
    while IFS= read -r -d '' file; do
        sha256sum "$file" >> "$evidence_dir/MANIFESTO-SHA256.txt"
    done < <(find "$evidence_dir" -maxdepth 1 -type f ! -name 'MANIFESTO-SHA256.txt' -print0 | sort -z)

    exit "$exit_code"
}

trap finish EXIT

if [[ "${SGP_ALLOW_ISOLATED_RESET:-}" != "YES" ]]; then
    printf 'Ensaio recusado: defina SGP_ALLOW_ISOLATED_RESET=YES.\n' >&2
    exit 2
fi

if [[ "${APP_ENV:-}" != "testing" ]]; then
    printf 'Ensaio recusado: APP_ENV deve ser testing.\n' >&2
    exit 2
fi

if [[ "${DB_CONNECTION:-}" != "pgsql" ]]; then
    printf 'Ensaio recusado: DB_CONNECTION deve ser pgsql.\n' >&2
    exit 2
fi

case "${DB_HOST:-}" in
    127.0.0.1|localhost|postgres) ;;
    *)
        printf 'Ensaio recusado: DB_HOST não identifica um banco local ou descartável.\n' >&2
        exit 2
        ;;
esac

case "${DB_DATABASE:-}" in
    sgp_rehearsal*) ;;
    *)
        printf 'Ensaio recusado: DB_DATABASE deve começar com sgp_rehearsal.\n' >&2
        exit 2
        ;;
esac

if [[ "${FILESYSTEM_DISK:-}" != "local" || "${SGP_PRIVATE_DISK:-}" != "local" ]]; then
    printf 'Ensaio recusado: os discos do ensaio devem ser locais e descartáveis.\n' >&2
    exit 2
fi

{
    printf 'BL-SGP-002 | Ensaio isolado da candidata v2.0.1-rc1\n'
    printf 'Início UTC: %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')"
    printf 'Commit: %s\n' "$(git rev-parse HEAD)"
    printf 'Branch/ref: %s\n' "${GITHUB_REF:-local}"
    printf 'APP_ENV: %s\n' "$APP_ENV"
    printf 'DB_CONNECTION: %s\n' "$DB_CONNECTION"
    printf 'DB_HOST: %s\n' "$DB_HOST"
    printf 'DB_PORT: %s\n' "$DB_PORT"
    printf 'DB_DATABASE: %s\n' "$DB_DATABASE"
    printf 'FILESYSTEM_DISK: %s\n' "$FILESYSTEM_DISK"
    printf 'SGP_PRIVATE_DISK: %s\n' "$SGP_PRIVATE_DISK"
    php --version | head -n 1
    composer --version
} > "$evidence_dir/00-ambiente.txt"

git diff --check
composer validate --no-check-publish
php artisan config:clear

printf 'Preparando banco descartável identificado como %s em %s.\n' "$DB_DATABASE" "$DB_HOST"
php artisan migrate:fresh --force 2>&1 | tee "$evidence_dir/01-migrations.txt"
php artisan migrate:status 2>&1 | tee "$evidence_dir/02-migration-status.txt"

run_critical_tests() {
    local junit_file=$1
    local output_file=$2

    php artisan test \
        --log-junit "$junit_file" \
        tests/Feature/ProductionOrganizationTransitionTest.php \
        tests/Feature/SelectiveProjectDataImportTest.php \
        tests/Feature/OrganizationDataBackfillTest.php \
        tests/Feature/OrganizationIntegrityTest.php \
        tests/Feature/OrganizationContextIsolationTest.php \
        tests/Feature/OrganizationFilesAndAuditTest.php \
        tests/Feature/AdaptiveProjectConfigurationTest.php \
        tests/Feature/InitiativeConversionTest.php \
        tests/Feature/ArtifactRevisionTest.php \
        2>&1 | tee "$output_file"
}

run_critical_tests "$evidence_dir/10-testes-criticos.xml" "$evidence_dir/10-testes-criticos.txt"

# This database is explicitly disposable. Recreate it instead of invoking down() on
# irreversible production-transition migrations. The second clean installation
# proves that the complete schema is repeatable without coupling the rehearsal to
# rollback support or to the dependency graph of future migrations.
php artisan migrate:fresh --force 2>&1 | tee "$evidence_dir/11-recriacao-completa.txt"
php artisan migrate:status 2>&1 | tee "$evidence_dir/12-status-apos-recriacao.txt"
run_critical_tests "$evidence_dir/15-testes-criticos-apos-recriacao.xml" "$evidence_dir/15-testes-criticos-apos-recriacao.txt"

php artisan test \
    --log-junit "$evidence_dir/20-suite-completa.xml" \
    2>&1 | tee "$evidence_dir/20-suite-completa.txt"

git status --short > "$evidence_dir/30-git-status-final.txt"
