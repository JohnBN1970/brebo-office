<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

/**
 * Validates a CRM-first Calc launch request independently of project identity.
 *
 * This is an input contract only. Calling code MUST check an authenticated Office
 * session, HTTPS, Origin, CSRF, CRM opportunity permissions, current revision
 * and issue a single-use server-side grant before accessing Calc.
 */
final class OfficeCalcOpportunityLaunchInput
{
    public static function parse(array $post): ?array
    {
        // CRM-first launch cannot be silently converted into project-first launch.
        if (array_key_exists('project_id', $post)
            || array_key_exists('calculation_id', $post)
            || array_key_exists('operator_id', $post)) {
            return null;
        }

        if (!array_key_exists('opportunity_id', $post)
            || !array_key_exists('revision', $post)
            || count($post) > 3) {
            return null;
        }

        $opportunityId = self::positiveInteger($post['opportunity_id']);
        $revision = self::positiveInteger($post['revision']);
        if ($opportunityId === null || $revision === null) {
            return null;
        }

        // Only explicitly permitted request keys; CSRF is verified separately.
        foreach (array_keys($post) as $key) {
            if (!in_array($key, ['opportunity_id', 'revision', 'csrf'], true)) {
                return null;
            }
        }

        return ['opportunity_id' => $opportunityId, 'revision' => $revision];
    }

    private static function positiveInteger(mixed $value): ?int
    {
        if (!is_string($value) || !preg_match('/^[1-9][0-9]*$/D', $value)) {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT);
        return is_int($number) && $number > 0 ? $number : null;
    }
}
