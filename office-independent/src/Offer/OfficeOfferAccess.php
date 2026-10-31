<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

use Brebo\Office\Project\OfficeNativeProjectAccess;
use PDO;

/** Explicit authorization boundary; fail closed: native access currently exposes canManage only. */
final class OfficeOfferAccess
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function canReadProject(int $operatorId, int $projectId): bool
    {
        return $operatorId > 0 && $projectId > 0
            && (new OfficeNativeProjectAccess($this->db))->canManage($operatorId, $projectId);
    }

    public function canManageProject(int $operatorId, int $projectId): bool
    {
        return $operatorId > 0 && $projectId > 0
            && (new OfficeNativeProjectAccess($this->db))->canManage($operatorId, $projectId);
    }
}
