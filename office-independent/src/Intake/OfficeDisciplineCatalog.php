<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Canonical service classification for an Office request.
 * Marketing and Finance are supporting processes, not service disciplines.
 * MJOP is reserved for future intake classification, not activated as a workflow.
 */
final class OfficeDisciplineCatalog
{
    public const DELIVERY = 'levering';
    public const INSTALLATION = 'montage';
    public const DELIVERY_INSTALLATION = 'levering_montage';
    public const CONSTRUCTION = 'bouw';
    public const ADVICE = 'advies';
    public const EXPERTISE = 'expertise';
    public const MJOP = 'mjop';

    public static function labels(): array
    {
        return [
            self::DELIVERY => 'Levering',
            self::INSTALLATION => 'Montage',
            self::DELIVERY_INSTALLATION => 'Levering en montage',
            self::CONSTRUCTION => 'Bouw',
            self::ADVICE => 'Advies',
            self::EXPERTISE => 'Expertise',
            self::MJOP => 'MJOP',
        ];
    }

    public static function active(): array
    {
        return [
            self::DELIVERY,
            self::INSTALLATION,
            self::DELIVERY_INSTALLATION,
            self::CONSTRUCTION,
            self::ADVICE,
            self::EXPERTISE,
        ];
    }

    public static function normalize(string $value): string
    {
        $normalized = strtolower(trim($value));
        $aliases = [
            'levering + montage' => self::DELIVERY_INSTALLATION,
            'montage en levering' => self::DELIVERY_INSTALLATION,
            'levering en montage' => self::DELIVERY_INSTALLATION,
            'expertise advisering' => self::EXPERTISE,
        ];
        return $aliases[$normalized] ?? $normalized;
    }

    public static function validate(array $values, bool $allowFuture = false): array
    {
        if ($values === []) {
            throw new \InvalidArgumentException('discipline_required');
        }
        $allowed = $allowFuture ? array_keys(self::labels()) : self::active();
        $result = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new \InvalidArgumentException('invalid_discipline');
            }
            $discipline = self::normalize($value);
            if (!in_array($discipline, $allowed, true)) {
                throw new \InvalidArgumentException('invalid_or_inactive_discipline');
            }
            $result[$discipline] = $discipline;
        }
        return array_values($result);
    }
}
