<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/**
 * Fail-closed verifier for a persisted CRM calculation acceptance.
 * Must be called inside the transfer service's existing transaction.
 * An opportunity marked won is NOT sufficient proof of acceptance.
 */
final class OfficeCalcPersistedCrmAcceptance
{
    public static function verify(PDO $db, array $acceptance): bool
    {
        if (!$db->inTransaction()) {
            return false;
        }
        if (!is_string($acceptance['crm_opportunity_id'] ?? null)
            || !ctype_digit($acceptance['crm_opportunity_id'])
            || (int)$acceptance['crm_opportunity_id'] < 1
            || !is_int($acceptance['calculation_id'] ?? null)
            || $acceptance['calculation_id'] < 1
            || !is_string($acceptance['accepted_revision'] ?? null)
            || trim($acceptance['accepted_revision']) === ''
            || ($acceptance['status'] ?? null) !== 'accepted') {
            return false;
        }
        // This dedicated record is deliberately separate from opportunity.stage.
        // Until the acceptance schema is installed, the transfer cannot proceed.
        $query = $db->prepare(
            'SELECT calculation_id, accepted_revision, status ' .
            'FROM office_crm_calculation_acceptances ' .
            'WHERE opportunity_id = ? FOR UPDATE'
        );
        $query->execute([(int)$acceptance['crm_opportunity_id']]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        return is_array($row)
            && (string)$row['calculation_id'] === (string)$acceptance['calculation_id']
            && hash_equals((string)$row['accepted_revision'], $acceptance['accepted_revision'])
            && $row['status'] === 'accepted';
    }
}
