<?php

declare(strict_types=1);

namespace Brebo\Office\Questions;

use DomainException;
use InvalidArgumentException;

/**
 * Mandatory object-scoped authorization boundary for Office conversations.
 *
 * The module adapter MUST check both operator identity and access to the
 * referenced entity; knowing an entity ID is never sufficient.
 */
final class OfficeConversationAccess
{
    /** @var \Closure(string,string,string,int,string):bool */
    private readonly \Closure $authorize;

    /**
     * @param callable(string,string,string,int,string):bool $authorize
     *        operatorId, module, entityType, entityId, action
     */
    public function __construct(callable $authorize)
    {
        $this->authorize = \Closure::fromCallable($authorize);
    }

    public function require(
        string $operatorId,
        string $module,
        string $entityType,
        int $entityId,
        string $action
    ): void {
        if ($operatorId === ''
            || $entityId < 1
            || !preg_match('/^[a-z_]{2,32}$/D', $module)
            || !preg_match('/^[a-z_]{2,64}$/D', $entityType)
            || !in_array($action, ['read', 'create', 'reply', 'resolve'], true)) {
            throw new InvalidArgumentException('Ongeldige berichtcontext');
        }

        if (!(($this->authorize)($operatorId, $module, $entityType, $entityId, $action))) {
            throw new DomainException('Geen toegang tot dit gesprek');
        }
    }
}
