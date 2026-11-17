<?php
declare(strict_types=1);

namespace Brebo\Office\Integrations\TwelveBuild;

/**
 * Converts verified, explicitly mapped 12Build records into intake candidates.
 * Does not assume an undocumented upstream JSON schema or write to CRM.
 */
final class TwelveBuildLeadStaging
{
    /**
     * @param list<array<string,mixed>> $records
     * @param callable(array):array $map One schema-verified upstream record to a normalized candidate
     * @return list<array<string,mixed>>
     */
    public static function candidates(array $records, callable $map): array
    {
        $seen = [];
        $out = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new \UnexpectedValueException('Invalid 12Build record.');
            }
            $candidate = $map($record);
            if (!is_array($candidate)) {
                throw new \UnexpectedValueException('Invalid mapped 12Build record.');
            }
            $externalId = $candidate['external_id'] ?? null;
            if (!is_string($externalId) || !preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', $externalId)) {
                throw new \UnexpectedValueException('Missing or unsafe 12Build external ID.');
            }
            $key = '12build:' . $externalId;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $candidate['source'] = '12build';
            $candidate['source_key'] = $key;
            $candidate['status'] = 'review_required';
            $out[] = $candidate;
        }
        return $out;
    }
}
