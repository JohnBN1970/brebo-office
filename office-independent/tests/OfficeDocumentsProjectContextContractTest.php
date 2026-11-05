<?php
declare(strict_types=1);
$source = file_get_contents(dirname(__DIR__) . '/public/office-documents.php');
if (!is_string($source)) {
    throw new RuntimeException('Documents page missing');
}
foreach ([
    "FILTER_VALIDATE_INT",
    "OfficeNativeProjectAccess.php",
    "->canRead(",
    "OfficeNewDocumentMenu::render('documenten', \\$documentScope)",
    "inspectionUrl",
    "http_response_code(404)"
] as $fragment) {
    if (!str_contains($source, $fragment)) {
        throw new RuntimeException('Missing documents context guard: ' . $fragment);
    }
}
echo "PASS DOCUMENTS_PROJECT_CONTEXT_CONTRACT\n";
