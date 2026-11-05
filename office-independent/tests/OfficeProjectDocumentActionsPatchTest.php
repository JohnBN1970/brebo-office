<?php
declare(strict_types=1);
$patcher = dirname(__DIR__) . '/deployment/hostinger/patch-project-document-actions.php';
$fixture = <<<'PHP'
<?php
$projectId = 42;
if ($canRead) {
  $inspectionActions = '<div class="project-related-actions"><a href="/office-test/inspecties?project_id=' . (int) $projectId . '">Inspecties</a>';
  if ($canManage) $inspectionActions .= 'Nieuwe inspectie';
}
PHP;
$file = tempnam(sys_get_temp_dir(), 'office-doc-actions-');
if ($file === false) throw new RuntimeException('temp file');
try {
    file_put_contents($file, $fixture);
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($patcher) . ' ' . escapeshellarg($file);
    exec($command, $output, $status);
    if ($status !== 0) throw new RuntimeException('patch failed: ' . implode(' ', $output));
    $result = file_get_contents($file);
    if (!str_contains($result, '/documenten?project_id=') || !str_contains($result, '/inspecties?project_id=')) throw new RuntimeException('links absent');
    if (!str_contains($result, 'if ($canManage)')) throw new RuntimeException('authorization flow modified');
    exec($command, $output, $status);
    if ($status !== 0 || file_get_contents($file) !== $result) throw new RuntimeException('not idempotent');
    file_put_contents($file, str_replace('project-related-actions', 'wrong-anchor', $fixture));
    exec($command, $output, $status);
    if ($status === 0) throw new RuntimeException('missing anchor accepted');
    echo "PASS PROJECT_DOCUMENT_ACTIONS_PATCH\n";
} finally {
    unlink($file);
}
