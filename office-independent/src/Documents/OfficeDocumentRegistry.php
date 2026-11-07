<?php
declare(strict_types=1);

namespace Brebo\Office\Documents;

use PDO;
use RuntimeException;
use InvalidArgumentException;

/** Standalone Office document identity and source/context registry. No Drupal dependency. */
final class OfficeDocumentRegistry
{
    public function __construct(private readonly PDO $db) {}

    /** Create schema through a separately authorized migration, never on request. */
    public static function schema(): array
    {
        return [
            'CREATE TABLE IF NOT EXISTS office_document_originals (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                storage_provider VARCHAR(40) NOT NULL,
                storage_key VARCHAR(255) NOT NULL,
                byte_size BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_sha256 (sha256),
                UNIQUE KEY uniq_storage (storage_provider, storage_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS office_documents (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                original_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                mime_type VARCHAR(150) NOT NULL,
                lifecycle_status VARCHAR(32) NOT NULL DEFAULT \'active\',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_original (original_id),
                CONSTRAINT fk_office_document_original FOREIGN KEY (original_id) REFERENCES office_document_originals(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS office_document_contexts (
                document_id BIGINT UNSIGNED NOT NULL,
                context_type VARCHAR(32) NOT NULL,
                context_id VARCHAR(100) NOT NULL,
                relation_role VARCHAR(32) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (document_id, context_type, context_id, relation_role),
                CONSTRAINT fk_office_document_context FOREIGN KEY (document_id) REFERENCES office_documents(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS office_document_sources (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                document_id BIGINT UNSIGNED NOT NULL,
                source_system VARCHAR(80) NOT NULL,
                source_external_id VARCHAR(255) DEFAULT NULL,
                received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_source_document (document_id),
                CONSTRAINT fk_office_document_source FOREIGN KEY (document_id) REFERENCES office_documents(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        ];
    }

    /**
     * Must be called only after authenticated intake, file validation, successful
     * immutable storage, and authorization. Never accept a client-supplied key.
     * @param array{storage_provider:string,storage_key:string,sha256:string,size:int} $stored
     */
    public function register(array $stored, string $title, string $filename, string $mime, string $sourceSystem, ?string $sourceExternalId = null): int
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $stored['sha256'])
            || $stored['storage_provider'] !== 'office_private'
            || $stored['storage_key'] !== 'originals/' . substr($stored['sha256'], 0, 2) . '/' . $stored['sha256']
            || $stored['size'] < 0 || $title === '' || strlen($title) > 255
            || $filename === '' || strlen($filename) > 255
            || $mime === '' || strlen($mime) > 150
            || $sourceSystem === '' || strlen($sourceSystem) > 80
            || ($sourceExternalId !== null && strlen($sourceExternalId) > 255)) {
            throw new InvalidArgumentException('invalid_document_registration');
        }
        $this->db->beginTransaction();
        try {
            $original = $this->db->prepare('INSERT INTO office_document_originals (sha256, storage_provider, storage_key, byte_size) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
            $original->execute([$stored['sha256'], $stored['storage_provider'], $stored['storage_key'], $stored['size']]);
            $originalId = (int) $this->db->lastInsertId();
            $verify = $this->db->prepare('SELECT storage_provider, storage_key, byte_size FROM office_document_originals WHERE id = ?');
            $verify->execute([$originalId]);
            $existing = $verify->fetch(PDO::FETCH_ASSOC);
            if (!$existing || $existing['storage_provider'] !== $stored['storage_provider']
                || $existing['storage_key'] !== $stored['storage_key']
                || (int) $existing['byte_size'] !== $stored['size']) {
                throw new RuntimeException('original_metadata_conflict');
            }
            $document = $this->db->prepare('INSERT INTO office_documents (original_id, title, original_filename, mime_type) VALUES (?, ?, ?, ?)');
            $document->execute([$originalId, $title, $filename, $mime]);
            $documentId = (int) $this->db->lastInsertId();
            $source = $this->db->prepare('INSERT INTO office_document_sources (document_id, source_system, source_external_id) VALUES (?, ?, ?)');
            $source->execute([$documentId, $sourceSystem, $sourceExternalId]);
            $this->db->commit();
            return $documentId;
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    /** Context identifiers are resolved and authorized by their owning Office module. */
    public static function allowedContextTypes(): array
    {
        return ['building', 'project', 'organization', 'contact', 'brebo', 'personnel'];
    }

    /** Call only after authorization against the requested business context. */
    public function attachContext(int $documentId, string $type, string $id, string $role = 'supporting'): void
    {
        if ($documentId < 1 || !in_array($type, self::allowedContextTypes(), true)
            || $id === '' || strlen($id) > 100
            || !in_array($role, ['origin', 'durable', 'supporting', 'contractual', 'correspondence', 'other'], true)) {
            throw new InvalidArgumentException('invalid_document_context');
        }
        $stmt = $this->db->prepare('INSERT IGNORE INTO office_document_contexts (document_id, context_type, context_id, relation_role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$documentId, $type, $id, $role]);
    }
}
