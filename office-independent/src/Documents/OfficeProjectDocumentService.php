<?php
declare(strict_types=1);

namespace Brebo\Office\Documents;

use PDO;
use RuntimeException;
use InvalidArgumentException;

/**
 * Project-scoped document application service. No HTTP exposure.
 * Authorization uses the existing OfficeNativeProjectAccess policy.
 */
final class OfficeProjectDocumentService
{
    public function __construct(
        private readonly PDO $db,
        private readonly OfficePrivateFileStorage $storage,
        private readonly OfficeDocumentRegistry $registry,
        private readonly \Brebo\Office\Project\OfficeNativeProjectAccess $projects
    ) {}

    /** Call only with a server-authenticated Office operator id and a validated upload tmp file. */
    public function upload(int $operatorId, int $projectId, string $tmpPath, string $filename, string $title): int
    {
        $this->requireProject($operatorId, $projectId, true);
        if ($tmpPath === '' || !is_uploaded_file($tmpPath) || !is_file($tmpPath)) {
            throw new InvalidArgumentException('validated_http_upload_required');
        }
        $size = filesize($tmpPath);
        if ($size === false || $size < 1 || $size > 25 * 1024 * 1024) {
            throw new InvalidArgumentException('invalid_document_size');
        }
        $filename = trim(basename(str_replace('\\', '/', $filename)));
        $title = trim($title);
        if ($filename === '' || $title === '' || strlen($filename) > 255 || strlen($title) > 255) {
            throw new InvalidArgumentException('invalid_document_metadata');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
        if (!is_string($mime) || !in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'text/plain'], true)) {
            throw new InvalidArgumentException('unsupported_document_type');
        }
        $stored = $this->storage->put($tmpPath);
        // Keep the unlinked document invisible if context attachment fails.
        // A compensating repair job can reconcile such incomplete registrations.
        $id = $this->registry->register($stored, $title, $filename, $mime, 'office_project_upload');
        $this->registry->attachContext($id, 'project', (string) $projectId, 'origin');
        return $id;
    }

    /** Returns the file path only after a trusted server-side Office project grant. */
    public function authorizedDownload(int $operatorId, int $projectId, int $documentId): array
    {
        $this->requireProject($operatorId, $projectId, false);
        if ($documentId < 1) {
            throw new InvalidArgumentException('invalid_document_id');
        }
        $query = $this->db->prepare(
            'SELECT d.original_filename, d.mime_type, o.storage_provider, o.storage_key
             FROM office_documents d
             JOIN office_document_originals o ON o.id = d.original_id
             JOIN office_document_contexts c ON c.document_id = d.id
             WHERE d.id = ? AND c.context_type = ? AND c.context_id = ? AND d.lifecycle_status = ?
             LIMIT 1'
        );
        $query->execute([$documentId, 'project', (string) $projectId, 'active']);
        $document = $query->fetch(PDO::FETCH_ASSOC);
        if (!$document || $document['storage_provider'] !== 'office_private') {
            throw new RuntimeException('document_not_found');
        }
        // Multi-context documents must not leak through a weaker project grant.
        $count = $this->db->prepare('SELECT COUNT(*) FROM office_document_contexts WHERE document_id = ? AND NOT (context_type = ? AND context_id = ?)');
        $count->execute([$documentId, 'project', (string) $projectId]);
        if ((int) $count->fetchColumn() > 0) {
            throw new RuntimeException('additional_context_authorization_required');
        }
        return ['path' => $this->storage->authorizedPath($document['storage_key']),
            'filename' => $document['original_filename'], 'mime_type' => $document['mime_type']];
    }

    private function requireProject(int $operatorId, int $projectId, bool $manage): void
    {
        if ($operatorId < 1 || $projectId < 1
            || !($manage ? $this->projects->canManage($operatorId, $projectId) : $this->projects->canRead($operatorId, $projectId))) {
            throw new RuntimeException('project_access_denied');
        }
    }
}
