<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Documents/OfficeDocumentAccessGate.php';
use Brebo\Office\Documents\OfficeDocumentAccessGate;

$gate = new OfficeDocumentAccessGate(static fn(string $actor, string $type, string $id, string $action): bool => $actor === 'employee-1' && $id === 'allowed');
$gate->requireAccess('employee-1', [['context_type' => 'project', 'context_id' => 'allowed']], 'read');
$denied = [
    ['employee-2', [['context_type' => 'project', 'context_id' => 'allowed']], 'read'],
    ['employee-1', [['context_type' => 'project', 'context_id' => 'allowed'], ['context_type' => 'building', 'context_id' => 'secret']], 'read'],
    ['employee-1', [], 'read'],
    ['employee-1', [['context_type' => 'project', 'context_id' => 'secret']], 'read'],
];
foreach ($denied as [$actor, $contexts, $action]) {
    try {
        $gate->requireAccess($actor, $contexts, $action);
        throw new LogicException('unexpected_document_access_grant');
    } catch (RuntimeException $e) {
        if ($e instanceof LogicException) { throw $e; }
        if ($e->getMessage() !== 'document_access_denied') { throw $e; }
    }
}
echo "OFFICE_DOCUMENT_ACCESS_GATE_OK\n";
