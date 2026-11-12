<?php
declare(strict_types=1);

namespace Brebo\Office\Quotation;

use DomainException;

/**
 * Pure rubric review rules. Approval is tied to an immutable source hash.
 * The caller must authorize the reviewer and persist audit events atomically.
 */
final class OfficeQuotationRubricReview
{
    public static function approve(array $rubric, array $checks, string $sourceHash, int $reviewerId): array
    {
        if ($reviewerId < 1 || !preg_match('/^[a-f0-9]{64}$/D', $sourceHash)) {
            throw new DomainException('Invalid reviewer or source version');
        }
        foreach (['description_checked', 'quantities_checked', 'prices_checked'] as $field) {
            if (($checks[$field] ?? null) !== true) {
                throw new DomainException('All rubric checks must be confirmed');
            }
        }
        if (empty($rubric['lines']) || !is_array($rubric['lines'])) {
            throw new DomainException('Cannot approve empty rubric');
        }
        $rubric['review'] = [
            'description_checked' => true,
            'quantities_checked' => true,
            'prices_checked' => true,
            'approved' => true,
            'source_hash' => $sourceHash,
            'reviewer_id' => $reviewerId,
        ];
        return $rubric;
    }

    public static function isApproved(array $rubric, string $currentSourceHash): bool
    {
        $review = $rubric['review'] ?? [];
        return is_array($review) && ($review['approved'] ?? false) === true
            && ($review['source_hash'] ?? null) === $currentSourceHash
            && ($review['description_checked'] ?? false) === true
            && ($review['quantities_checked'] ?? false) === true
            && ($review['prices_checked'] ?? false) === true;
    }

    public static function allApproved(array $rubrics, string $currentSourceHash): bool
    {
        if ($rubrics === []) return false;
        foreach ($rubrics as $rubric) {
            if (!is_array($rubric) || !self::isApproved($rubric, $currentSourceHash)) return false;
        }
        return true;
    }
}
