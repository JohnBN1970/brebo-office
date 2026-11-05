<?php
declare(strict_types=1);

/**
 * Minimal, idempotent patch for the already-authorized native Office-test project page.
 * Operates on a supplied copy, never on a live file directly.
 */
if ($argc !== 2 || !is_file($argv[1])) {
    fwrite(STDERR, "Usage: php patch-project-document-actions.php <candidate-file>\n");
    exit(2);
}
$file = $argv[1];
$source = file_get_contents($file);
if (!is_string($source)) exit(3);
$marker = 'BREBO_PROJECT_DOCUMENT_ACTIONS_20261105';
if (str_contains($source, $marker)) {
    echo "PROJECT_DOCUMENT_ACTIONS_ALREADY_PRESENT\n";
    exit(0);
}
$anchor = <<<'PHP'
  $inspectionActions = '<div class="project-related-actions"><a href="/office-test/inspecties?project_id=' . (int) $projectId . '">Inspecties</a>';
PHP;
if (substr_count($source, $anchor) !== 1) {
    fwrite(STDERR, "PROJECT_DOCUMENT_ANCHOR_NOT_UNIQUE\n");
    exit(4);
}
$replacement = <<<'PHP'
  // BREBO_PROJECT_DOCUMENT_ACTIONS_20261105: authenticated project context.
  $inspectionActions = '<div class="project-related-actions"><a href="/office-test/documenten?project_id=' . (int) $projectId . '">Documenten</a>'
    . '<a href="/office-test/inspecties?project_id=' . (int) $projectId . '">Inspectierapporten</a>';
PHP;
$updated = str_replace($anchor, $replacement, $source);
if (file_put_contents($file, $updated, LOCK_EX) === false) exit(5);
echo "PROJECT_DOCUMENT_ACTIONS_PATCHED\n";
