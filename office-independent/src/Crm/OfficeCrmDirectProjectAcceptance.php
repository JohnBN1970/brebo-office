<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

use PDO;
require_once __DIR__.'/OfficeCrmDirectProjectTransfer.php';
require_once __DIR__.'/OfficeCrmNativeProjectAdapter.php';

/**
 * Explicit application service. The HTTP controller must validate the current
 * Office session, request CSRF and the user-intended accepted opportunity.
 * This does not start work or create a calculation / locked budget.
 */
final class OfficeCrmDirectProjectAcceptance
{
    public function __construct(private readonly PDO $db) {}

    public function transferForOperator(int $operatorId,int $opportunityId): int
    {
        return (new OfficeCrmDirectProjectTransfer($this->db))->transfer(
            $opportunityId,
            new OfficeCrmNativeProjectAdapter($operatorId)
        );
    }
}
