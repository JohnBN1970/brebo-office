<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

/**
 * Resolve exactly one authorized historical rate from server-side Office records.
 * Rate must represent authorized employee actual cost, NOT the locked calculation\n * budget rate. The two rates serve different financial purposes.\n * No implicit default, latest-rate fallback, or client-supplied rate.
 */
final class OfficeApprovedLabourRatePolicy
{
    public static function resolve(int $personId, int $projectId, string $workDate, array $rates): int
    {
        if ($personId < 1 || $projectId < 1
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $workDate)
            || !checkdate((int)substr($workDate,5,2),(int)substr($workDate,8,2),(int)substr($workDate,0,4))) {
            throw new \InvalidArgumentException('Invalid rate lookup context');
        }
        $matching = [];
        foreach ($rates as $rate) {
            if (!is_array($rate) || ($rate['person_id'] ?? null) !== $personId
                || ($rate['project_id'] ?? null) !== $projectId) continue;
            $from = $rate['valid_from'] ?? null;
            $until = $rate['valid_until'] ?? null;
            $cents = $rate['hourly_rate_cents'] ?? null;
            $authorized = $rate['authorized'] ?? false;
            if (!is_string($from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$from)
                || !checkdate((int)substr($from,5,2),(int)substr($from,8,2),(int)substr($from,0,4))
                || ($until !== null && (!is_string($until)
                    || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$until)
                    || !checkdate((int)substr($until,5,2),(int)substr($until,8,2),(int)substr($until,0,4))))
                || !is_int($cents) || $cents < 1 || $authorized !== true) {
                throw new \DomainException('Invalid or unauthorized rate evidence');
            }
            if ($until !== null && $until < $from) throw new \DomainException('Invalid rate interval');
            if ($from <= $workDate && ($until === null || $workDate <= $until)) $matching[] = $cents;
        }
        if (count($matching) !== 1) throw new \DomainException('Exactly one authorized effective labour rate required');
        return $matching[0];
    }
}
