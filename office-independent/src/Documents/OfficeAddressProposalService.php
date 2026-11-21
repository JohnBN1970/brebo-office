<?php
declare(strict_types=1);

namespace Brebo\Office\Documents;

use Brebo\Office\Buildings\OfficeIntakeBuildingResolver;
use InvalidArgumentException;
use PDO;

/**
 * Authorized application boundary: caller must have already checked document
 * read/manage permissions and the private original integrity.
 * No inferred address or resident is written to canonical tables.
 */
final class OfficeAddressProposalService
{
    public function __construct(
        private readonly PDO $db,
        private readonly OfficeSourceAddressExtractor $extractor,
        private readonly OfficeIntakeBuildingResolver $resolver
    ) {}

    /** @return array{created:int,existing:int,issues:array} */
    public function propose(int $documentId, string $privateOriginalPath, string $mimeType, string $filename): array
    {
        if ($documentId < 1) throw new InvalidArgumentException('invalid_document_id');
        if (!is_file($privateOriginalPath) || is_link($privateOriginalPath) || !is_readable($privateOriginalPath)) {
            throw new InvalidArgumentException('source_unavailable');
        }
        $size = filesize($privateOriginalPath);
        if ($size === false || $size < 1 || $size > 25 * 1024 * 1024) {
            throw new InvalidArgumentException('source_size_invalid');
        }
        $actualHash = hash_file('sha256', $privateOriginalPath);
        if (!is_string($actualHash)) {
            throw new InvalidArgumentException('source_integrity_mismatch');
        }
        $known = $this->db->prepare(
            'SELECT d.original_filename, d.mime_type, o.sha256, o.storage_provider, o.storage_key, o.byte_size FROM office_documents d
             JOIN office_document_originals o ON o.id = d.original_id
             WHERE d.id = ? AND d.lifecycle_status = ? LIMIT 1'
        );
        $known->execute([$documentId, 'active']);
        $source = $known->fetch(PDO::FETCH_ASSOC);
        if (!$source || $source['original_filename'] !== $filename || $source['mime_type'] !== $mimeType
            || $source['storage_provider'] !== 'office_private'
            || $source['storage_key'] !== 'originals/' . substr($actualHash, 0, 2) . '/' . $actualHash
            || (int)$source['byte_size'] !== $size
            || !hash_equals((string)$source['sha256'], $actualHash)) {
            throw new InvalidArgumentException('source_integrity_mismatch');
        }

        $extracted = $this->extractor->extract($privateOriginalPath, $mimeType, $filename);
        $insert = $this->db->prepare(
            'INSERT IGNORE INTO office_document_address_proposals
             (document_id, source_location, evidence_hash, address_line, postal_code, city,
              resident_name, resident_email, resident_phone, suggested_building_id, match_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $created = 0;
        $existing = 0;
        foreach ($extracted['rows'] as $entry) {
            $value = $entry['data'];
            $match = $this->resolver->resolve($value['address'], $value['postal_code'], $value['city']);
            $status = $match['status'] === 'linked' ? 'exact_candidate'
                : ($match['status'] === 'ambiguous' ? 'ambiguous' : 'unmatched');
            // Source location is part of identity: two residents with the same
            // name in different rows must remain separately reviewable.
            $identity = hash('sha256', json_encode([$entry['location'], $value], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $insert->execute([
                $documentId, $entry['location'], $identity, $value['address'], $value['postal_code'], $value['city'],
                $value['name'] ?: null, $value['email'] ?: null, $value['phone'] ?: null,
                $match['building_id'], $status,
            ]);
            if ($insert->rowCount() > 0) $created++;
            else $existing++;
        }
        return ['created' => $created, 'existing' => $existing, 'issues' => $extracted['issues']];
    }
}
