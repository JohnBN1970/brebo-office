<?php
declare(strict_types=1);
namespace Brebo\Office\Documents;

/** Additive schema proposal. Installation is explicit, never from a request. */
final class OfficeDossierArchiveSchema
{
    public static function statements(string $driver = 'mysql'): array
    {
        if (!in_array($driver, ['mysql','sqlite'], true)) throw new \InvalidArgumentException('Unsupported driver');
        $id = $driver === 'mysql' ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
        return [
            "CREATE TABLE office_dossier_archives (
                id $id, scope_type VARCHAR(24) NOT NULL, scope_id BIGINT NOT NULL,
                archive_version INTEGER NOT NULL, status VARCHAR(32) NOT NULL,
                revision INTEGER NOT NULL DEFAULT 1, storage_key VARCHAR(255) NULL,
                manifest_sha256 CHAR(64) NULL, archive_sha256 CHAR(64) NULL,
                created_at VARCHAR(32) NOT NULL, updated_at VARCHAR(32) NOT NULL,
                UNIQUE(scope_type,scope_id,archive_version)
            )$tail",
            "CREATE TABLE office_dossier_archive_events (
                id $id, archive_id BIGINT NOT NULL, revision INTEGER NOT NULL,
                actor_id VARCHAR(128) NOT NULL, from_status VARCHAR(32) NOT NULL,
                to_status VARCHAR(32) NOT NULL, reason VARCHAR(255) NOT NULL,
                created_at VARCHAR(32) NOT NULL,
                UNIQUE(archive_id,revision)
            )$tail",
            "CREATE INDEX idx_dossier_archive_scope ON office_dossier_archives(scope_type,scope_id,status)"
        ];
    }
}
