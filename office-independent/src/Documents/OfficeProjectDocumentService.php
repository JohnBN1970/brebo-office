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
        if (!is_string($mime) || !in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream', 'message/rfc822'], true)) {
            throw new InvalidArgumentException('unsupported_document_type');
        }
        if (in_array($mime, ['application/zip', 'application/octet-stream'], true) && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw new InvalidArgumentException('unsupported_document_type');
        }
        if (in_array($mime, ['text/csv', 'application/csv', 'application/vnd.ms-excel', 'message/rfc822'], true)
            && !in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), ['csv', 'eml'], true)) {
            throw new InvalidArgumentException('unsupported_document_type');
        }
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === 'xlsx') {
            if (!class_exists(\ZipArchive::class)) {
                throw new InvalidArgumentException('xlsx_validation_unavailable');
            }
            $zip = new \ZipArchive();
            if ($zip->open($tmpPath, \ZipArchive::RDONLY) !== true) {
                throw new InvalidArgumentException('invalid_xlsx_container');
            }
            try {
                if ($zip->locateName('xl/worksheets/sheet1.xml') === false
                    && $zip->locateName('xl/workbook.xml') === false) {
                    throw new InvalidArgumentException('invalid_xlsx_structure');
                }
            } finally {
                $zip->close();
            }
        } elseif (in_array($mime, ['application/zip', 'application/octet-stream',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], true)) {
            throw new InvalidArgumentException('unsupported_document_type');
        }
        $stored = $this->storage->put($tmpPath);
        // Keep the unlinked document invisible if context attachment fails.
        // A compensating repair job can reconcile such incomplete registrations.
        $id = $this->registry->register($stored, $title, $filename, $mime, 'office_project_upload');
        $this->registry->attachContext($id, 'project', (string) $projectId, 'origin');
        return $id;
    }

    /**
     * Authorized, review-only document evidence. Call after the proposal schema
     * has been explicitly installed. No canonical building/resident mutations.
     */
    public function proposeProjectAddresses(int $operatorId, int $projectId, int $documentId): array
    {
        $this->requireProject($operatorId, $projectId, true);
        $document = $this->authorizedDownload($operatorId, $projectId, $documentId);
        require_once __DIR__ . '/OfficeSourceAddressExtractor.php';
        require_once __DIR__ . '/OfficeAddressProposalService.php';
        require_once dirname(__DIR__) . '/Buildings/OfficeIntakeBuildingResolver.php';
        $service = new OfficeAddressProposalService(
            $this->db,
            new OfficeSourceAddressExtractor(),
            new \Brebo\Office\Buildings\OfficeIntakeBuildingResolver($this->db)
        );
        return $service->propose(
            $documentId, $document['path'], $document['mime_type'], $document['filename']
        );
    }

    /** Authorized project-only evidence review; never changes canonical records. */
    public function projectAddressProposals(int $operatorId, int $projectId, int $documentId): array
    {
        $this->authorizedDownload($operatorId, $projectId, $documentId);
        require_once __DIR__ . '/OfficeAddressProposalReviewService.php';
        return (new OfficeAddressProposalReviewService($this->db))->pendingForDocument($documentId);
    }

    /** Explicit human review, scoped to the same document and project grant. */
    public function reviewProjectAddressProposal(
        int $operatorId, int $projectId, int $documentId, int $proposalId, string $decision
    ): bool {
        $this->requireProject($operatorId, $projectId, true);
        $this->authorizedDownload($operatorId, $projectId, $documentId);
        require_once __DIR__ . '/OfficeAddressProposalReviewService.php';
        return (new OfficeAddressProposalReviewService($this->db))->decide($documentId, $proposalId, $decision);
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
