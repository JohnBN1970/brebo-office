<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

use PDO;

/** Explicit Office module grants; never infer grants from a project role. */
final class OfficeOfferModulePermission
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function allows(int $operatorId, string $permission): bool
    {
        if ($operatorId < 1 || !in_array($permission, [
            'read', 'create', 'update', 'issue', 'record_acceptance', 'link_change', 'manage',
        ], true)) {
            return false;
        }

        $query = $this->db->prepare(
            "SELECT 1 FROM office_module_permissions p
             INNER JOIN office_operator_accounts a ON a.id = p.operator_id
             WHERE p.operator_id = :operator_id AND p.module = 'offers'
               AND p.permission = :permission AND a.active = 1 LIMIT 1"
        );
        $query->execute([':operator_id' => $operatorId, ':permission' => $permission]);
        return $query->fetchColumn() !== false;
    }
}
