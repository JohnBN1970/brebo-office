<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Project/OfficeNativeProjectAccess.php';
require_once dirname(__DIR__) . '/src/Documents/OfficeProjectDocumentService.php';
$method = new ReflectionMethod(Brebo\Office\Project\OfficeNativeProjectAccess::class, 'canRead');
if (!$method->isPublic() || $method->getNumberOfRequiredParameters() !== 2) {
    throw new RuntimeException('project_read_access_contract_invalid');
}
$source = file_get_contents(dirname(__DIR__) . '/src/Project/OfficeNativeProjectAccess.php');
if (!str_contains($source, "m.permission IN ('read', 'manage')") || !str_contains($source, 'a.active=1')) {
    throw new RuntimeException('project_read_access_policy_invalid');
}
echo "OFFICE_PROJECT_DOCUMENT_ACCESS_CONTRACT_OK\n";
