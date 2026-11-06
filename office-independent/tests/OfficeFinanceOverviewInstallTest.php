<?php
declare(strict_types=1);
define('OFFICE_OVERVIEW_LIBRARY_ONLY', true);
require dirname(__DIR__).'/deployment/hostinger/office-finance-overview-install.php';
$changes = json_decode(file_get_contents(dirname(__DIR__).'/deployment/hostinger/office-finance-overview-changes.json'), true, 32, JSON_THROW_ON_ERROR);
if (array_keys($changes) !== ['src/Finance/OfficeFinanceView.php','public/office-finance.php']) throw new RuntimeException('Unexpected patch scope');
foreach ($changes as $path=>$chunks) {
  $after = file_get_contents(dirname(__DIR__).'/'.$path);
  $before = $after;
  foreach (array_reverse($chunks) as $chunk) {
    if (substr_count($before, $chunk['after']) !== 1) throw new RuntimeException('New source not represented by patch');
    $before = str_replace($chunk['after'], $chunk['before'], $before);
  }
  if (overviewTransform($before, $chunks) !== $after) throw new RuntimeException('Patch parity');
  $hotfix = "// unrelated installed hotfix\n".$before;
  if (overviewTransform($hotfix, $chunks) !== "// unrelated installed hotfix\n".$after) throw new RuntimeException('Hotfix lost');
  try { overviewTransform($after, $chunks); throw new LogicException('Reapplication accepted'); }
  catch (RuntimeException $expected) {}
  try { overviewTransform('unknown source', $chunks); throw new LogicException('Unknown source accepted'); }
  catch (RuntimeException $expected) {}
}
echo "OFFICE_FINANCE_OVERVIEW_INSTALL_OK\n";
