<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmLegacyImportCommand;

require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyExportValidator.php';
require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyImportRepository.php';
require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyImportCommand.php';

$file = tempnam(sys_get_temp_dir(), 'crm-review-');
if ($file === false) {
  throw new RuntimeException('Cannot create temporary export.');
}
try {
  $rows = [['legacy_node_id' => 12, 'title' => 'Existing opportunity', 'stage' => 'Lead', 'owner_uid' => 1]];
  file_put_contents($file, json_encode($rows, JSON_THROW_ON_ERROR));
  putenv('OFFICE_CRM_IMPORT_CONFIRM');
  if (WebsiteCrmLegacyImportCommand::run(['import-legacy-crm.php', $file]) !== 0) {
    throw new RuntimeException('Expected dry run to pass.');
  }
  file_put_contents($file, json_encode([['legacy_node_id' => 0]], JSON_THROW_ON_ERROR));
  if (WebsiteCrmLegacyImportCommand::run(['import-legacy-crm.php', $file]) !== 1) {
    throw new RuntimeException('Expected invalid export to be rejected.');
  }
  if (WebsiteCrmLegacyImportCommand::run(['import-legacy-crm.php']) !== 2) {
    throw new RuntimeException('Expected missing input to be rejected.');
  }
}
finally {
  unlink($file);
}
echo "Legacy CRM import CLI dry-run checks passed.\n";
