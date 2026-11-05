<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeRoleSettingsModuleCatalog.php';

use Brebo\Office\Offer\OfficeRoleSettingsModuleCatalog;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
};

$assert(OfficeRoleSettingsModuleCatalog::modules() === ['offers', 'finance'], 'Explicit module list');
$offers = OfficeRoleSettingsModuleCatalog::labels('offers');
$finance = OfficeRoleSettingsModuleCatalog::labels('finance');
$assert(count($offers) === 7, 'Existing offer permissions preserved');
$assert(array_keys($finance) === ['payment_batch.create', 'payment_batch.review', 'payment_batch.release'], 'Finance rights');
$assert(OfficeRoleSettingsModuleCatalog::allows('offers', ['read', 'issue']), 'Offer permissions accepted');
$assert(OfficeRoleSettingsModuleCatalog::allows('finance', ['payment_batch.create', 'payment_batch.review']), 'Finance permissions accepted');
$assert(OfficeRoleSettingsModuleCatalog::allows('finance', []), 'Empty permission list accepted for disabled module');
$assert(!OfficeRoleSettingsModuleCatalog::allows('finance', ['manage']), 'No generic finance manage');
$assert(!OfficeRoleSettingsModuleCatalog::allows('finance', ['payment_batch.submit']), 'No bank submission');
$assert(!OfficeRoleSettingsModuleCatalog::allows('offers', ['payment_batch.release']), 'No cross-module rights');
$assert(!OfficeRoleSettingsModuleCatalog::allows('unknown', []), 'Unknown module denied');
$assert(!OfficeRoleSettingsModuleCatalog::allows('finance', [null]), 'Non-string denied');
$assert(!OfficeRoleSettingsModuleCatalog::allows('finance', [['payment_batch.release']]), 'Nested input denied');
$assert(OfficeRoleSettingsModuleCatalog::labels('unknown') === [], 'Unknown labels empty');

echo "OfficeRoleSettingsModuleCatalogTest OK ($checks checks)\n";
