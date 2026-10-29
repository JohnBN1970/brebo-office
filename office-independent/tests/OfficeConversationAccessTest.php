<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Questions/OfficeConversationAccess.php';

use Brebo\Office\Questions\OfficeConversationAccess;

$checks = 0;
$allow = new OfficeConversationAccess(
    static fn(string $operator, string $module, string $type, int $id, string $action): bool =>
        $operator === 'alice' && $module === 'finance' && $type === 'invoice'
        && $id === 42 && in_array($action, ['read', 'reply'], true)
);

$allow->require('alice', 'finance', 'invoice', 42, 'read');
$checks++;

foreach ([
    ['bob', 'finance', 'invoice', 42, 'read', DomainException::class],
    ['alice', 'finance', 'invoice', 43, 'read', DomainException::class],
    ['alice', 'finance', 'invoice', 42, 'resolve', DomainException::class],
    ['alice', 'finance', 'invoice', 42, 'create', DomainException::class],
    ['', 'finance', 'invoice', 42, 'read', InvalidArgumentException::class],
    ['alice', 'finance', 'invoice', 42, 'delete', InvalidArgumentException::class],
    ['alice', 'finance', 'invoice', 0, 'read', InvalidArgumentException::class],
] as [$operator, $module, $type, $id, $action, $expected]) {
    try {
        $allow->require($operator, $module, $type, $id, $action);
        throw new RuntimeException('Access check unexpectedly passed');
    } catch (Throwable $exception) {
        if (!$exception instanceof $expected) {
            throw $exception;
        }
        $checks++;
    }
}

echo "OfficeConversationAccess: {$checks} checks passed\n";
