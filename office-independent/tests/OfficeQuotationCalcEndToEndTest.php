<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Quotation/OfficeQuotationCalcPostController.php';
use Brebo\Office\Quotation\OfficeQuotationCalcPostController;
use Brebo\Office\Quotation\OfficeQuotationCalcGenerationService;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('pdo_sqlite required');
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE office_crm_relations (id INTEGER PRIMARY KEY, status TEXT);
CREATE TABLE office_crm_opportunities (id INTEGER PRIMARY KEY, relation_id INTEGER);
CREATE TABLE office_quotations (id INTEGER PRIMARY KEY AUTOINCREMENT, relation_id INTEGER, calculation_id INTEGER, calculation_version INTEGER, quotation_type TEXT, title TEXT, status TEXT, version INTEGER, amount_ex_vat_cents INTEGER, created_by INTEGER);
CREATE TABLE office_quotation_sections (id INTEGER PRIMARY KEY AUTOINCREMENT, quotation_id INTEGER, section_key TEXT, sort_order INTEGER, enabled INTEGER, body TEXT, provenance TEXT);
CREATE TABLE office_quotation_versions (id INTEGER PRIMARY KEY AUTOINCREMENT, quotation_id INTEGER, version INTEGER, snapshot_json TEXT, snapshot_sha256 TEXT, created_by INTEGER);
INSERT INTO office_crm_relations VALUES (7, 'active');
INSERT INTO office_crm_opportunities VALUES (9, 7);");
$csrf = str_repeat('a', 64);
$intent = ['action'=>'generate_quotation','calculation_id'=>'12','calculation_version'=>'3','csrf'=>$csrf];
$resolver = static function (int $operatorId, int $calcId, int $version): array {
    if ($operatorId !== 5 || $calcId !== 12 || $version !== 3) throw new DomainException('Scope mismatch');
    return ['relation_id'=>7,'opportunity_id'=>9,'title'=>'Kozijnen','sales_snapshot'=>[
        'calculation_id'=>12,'version'=>3,'sales_lines'=>[
            ['description'=>'Kozijnen leveren','quantity'=>2,'unit'=>'st','amount_ex_vat_cents'=>120000,'cost_price'=>123,'margin'=>999],
            ['description'=>'Montage','quantity'=>8,'unit'=>'uur','amount_ex_vat_cents'=>64000,'labour'=>42],
        ],
    ]];
};
$controller = new OfficeQuotationCalcPostController($db);
$url = $controller->handle('POST', 5, $intent, $csrf, $resolver);
if ($url !== OfficeQuotationCalcGenerationService::editorUrl(1)) throw new RuntimeException('Wrong editor URL');
$row = $db->query('SELECT * FROM office_quotations WHERE id=1')->fetch(PDO::FETCH_ASSOC);
if (!$row || (int)$row['amount_ex_vat_cents'] !== 184000 || $row['status'] !== 'draft') throw new RuntimeException('Wrong quotation draft');
$sections = $db->query("SELECT body FROM office_quotation_sections WHERE section_key='price'")->fetchAll(PDO::FETCH_COLUMN);
if (count($sections)!==1 || !str_contains($sections[0], '600,00') || !str_contains($sections[0], '80,00')) throw new RuntimeException('Unit prices missing');
$json = (string)$db->query('SELECT snapshot_json FROM office_quotation_versions WHERE quotation_id=1')->fetchColumn();
foreach (['cost_price','margin','labour'] as $secret) {
    if (str_contains($json, '"'.$secret.'"')) throw new RuntimeException('Internal cost leaked: '.$secret);
}
$stored = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
if (count($stored['rubric_proposals'] ?? []) < 1) throw new RuntimeException('Rubric proposals missing');
$wrong = $intent; $wrong['csrf'] = str_repeat('b', 64);
try {
    $controller->handle('POST', 5, $wrong, $csrf, $resolver);
    throw new RuntimeException('Bad CSRF accepted');
} catch (DomainException $e) {
    if ($e->getMessage()==='Bad CSRF accepted') throw $e;
}
if ((int)$db->query('SELECT COUNT(*) FROM office_quotations')->fetchColumn() !== 1) throw new RuntimeException('Invalid request created draft');
$wrongRevision = $intent;
$wrongRevision['calculation_version'] = '4';
try {
    $controller->handle('POST', 5, $wrongRevision, $csrf, $resolver);
    throw new RuntimeException('Mismatched Calc revision accepted');
} catch (DomainException $e) {
    if ($e->getMessage() === 'Mismatched Calc revision accepted') throw $e;
}
$wrongRelationResolver = static function (int $operatorId, int $calcId, int $version) use ($resolver): array {
    $authorized = $resolver($operatorId, $calcId, $version);
    $authorized['relation_id'] = 8;
    return $authorized;
};
try {
    $controller->handle('POST', 5, $intent, $csrf, $wrongRelationResolver);
    throw new RuntimeException('Mismatched CRM relation accepted');
} catch (DomainException $e) {
    if ($e->getMessage() === 'Mismatched CRM relation accepted') throw $e;
}
if ((int)$db->query('SELECT COUNT(*) FROM office_quotations')->fetchColumn() !== 1) {
    throw new RuntimeException('Rejected handoff created an extra draft');
}
echo "OFFERTSTUDIO_CALC_END_TO_END=PASS\n";
