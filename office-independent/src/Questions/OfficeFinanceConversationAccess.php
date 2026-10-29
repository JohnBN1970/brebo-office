<?php

declare(strict_types=1);

namespace Brebo\Office\Questions;

use Brebo\Office\Finance\OfficeFinanceAccess;
use Brebo\Office\Finance\OfficeFinanceDraftRegister;
use PDO;

final class OfficeFinanceConversationAccess
{
    public static function create(PDO $db): OfficeConversationAccess
    {
        return new OfficeConversationAccess(
            static function (
                string $operatorId,
                string $module,
                string $entityType,
                int $entityId,
                string $action
            ) use ($db): bool {
                if ($module !== 'finance' || $entityType !== 'invoice') {
                    return false;
                }

                try {
                    (new OfficeFinanceDraftRegister($db))
                        ->findVisible($operatorId, $entityId);
                } catch (\DomainException | \OutOfBoundsException $e) {
                    return false;
                }

                if ($action === 'read') {
                    return true;
                }

                if (in_array($action, ['create', 'reply'], true)) {
                    return (new OfficeFinanceAccess($db, $operatorId))
                        ->allows('draft_edit');
                }

                return false;
            }
        );
    }
}
