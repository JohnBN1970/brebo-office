<?php
declare(strict_types=1);

namespace Brebo\Office\Crm;

/**
 * Decision only: never creates a project or changes CRM records.
 * Caller must resolve the customer against verified CRM relations, not names alone.
 */
final class OfficeCrmLeadRouting
{
    public const LEAD = 'lead';
    public const OPPORTUNITY = 'opportunity';
    public const AWARDED = 'awarded';
    public const REVIEW = 'review';

    /**
     * @param string $match none|customer|prospect|ambiguous
     * @param bool $acceptedOrder verified, recorded customer acceptance
     * @return string routing destination; side effects belong to an audited service
     */
    public static function destination(string $match, bool $acceptedOrder): string
    {
        if (!in_array($match, ['none', 'customer', 'prospect', 'ambiguous'], true)) {
            throw new \InvalidArgumentException('Unknown relation match.');
        }
        if ($match === 'ambiguous') {
            return self::REVIEW;
        }
        if ($acceptedOrder) {
            return $match === 'customer' ? self::AWARDED : self::REVIEW;
        }
        return $match === 'customer' ? self::OPPORTUNITY : self::LEAD;
    }

    /** Only non-converted leads belong on the active lead board. */
    public static function appearsInLeadBoard(string $destination): bool
    {
        return $destination === self::LEAD;
    }
}
