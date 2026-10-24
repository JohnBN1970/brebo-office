<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Ui/OfficeModuleTabs.php';
use Brebo\Office\Ui\OfficeModuleTabs;
$tabs = [
  ['key'=>'companies','label'=>'Bedrijven','url'=>'/office-test/crm/bedrijven','enabled'=>true],
  ['key'=>'contacts','label'=>'Contactpersonen','url'=>'/office-test/crm/contactpersonen','enabled'=>false],
  ['key'=>'bad','label'=>'<script>','url'=>'javascript:alert(1)','enabled'=>true],
  ['key'=>'requests','label'=>'Aanvragen','url'=>'/office-test/crm/aanvragen?page=1&sort=desc','enabled'=>true],
];
$html = OfficeModuleTabs::render($tabs, 'requests');
foreach (['aria-label="Moduleonderdelen"','aria-current="page"','Aanvragen','&amp;sort=desc'] as $needle) {
  if (!str_contains($html, $needle)) throw new RuntimeException('Missing tab markup: '.$needle);
}
foreach (['Contactpersonen','<script>','javascript:','is-active" href="/office-test/crm/bedrijven"'] as $needle) {
  if (str_contains($html, $needle)) throw new RuntimeException('Unsafe or incorrect tab: '.$needle);
}
if (OfficeModuleTabs::render([], 'none') !== '') throw new RuntimeException('Empty tabs should render nothing');
if (!str_contains(OfficeModuleTabs::css(), 'overflow-x:auto')) throw new RuntimeException('Missing responsive overflow');
echo "OfficeModuleTabs OK\n";
