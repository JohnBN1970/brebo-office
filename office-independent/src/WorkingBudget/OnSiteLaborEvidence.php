<?php
declare(strict_types=1);

namespace Brebo\Office\WorkingBudget;

use InvalidArgumentException;

/**
 * Provisional OnSite presence evidence, never approved or posted labor costs.
 * No labor rate may be inferred from a clock session.
 */
final class OnSiteLaborEvidence
{
    public static function summarize(array $overview, int $projectId): array
    {
        if ($projectId < 1) throw new InvalidArgumentException('Invalid project.');
        if (($overview['available'] ?? false) !== true) {
            return ['state'=>'unavailable','session_count'=>null,
                'duration_seconds'=>null,'labor_actual_cents'=>null];
        }
        $sessions = $overview['sessions'] ?? null;
        $exceptions = $overview['exceptions'] ?? null;
        if (!is_array($sessions) || !is_array($exceptions)) {
            throw new InvalidArgumentException('Invalid OnSite evidence.');
        }
        $seconds = 0;
        foreach ($sessions as $session) {
            if (!is_array($session) || ($session['project_id'] ?? null) !== $projectId) {
                throw new InvalidArgumentException('Cross-project OnSite evidence.');
            }
            $duration = $session['duration_seconds'] ?? null;
            if (!is_int($duration) || $duration < 0 || $duration > PHP_INT_MAX - $seconds) {
                throw new InvalidArgumentException('Invalid session duration.');
            }
            $seconds += $duration;
        }
        return [
            'state'=>$exceptions === [] ? 'provisional' : 'exceptions_present',
            'session_count'=>count($sessions),
            'duration_seconds'=>$seconds,
            'labor_actual_cents'=>null,
        ];
    }
}
