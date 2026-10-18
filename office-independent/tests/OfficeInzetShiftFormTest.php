<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftForm.php';
$html = \Brebo\Office\Inzet\OfficeInzetShiftForm::render(
  [['id'=>17,'title'=>'Test & <Project>']],
  [['id'=>8,'display_name'=>'Medewerker <X>','personnel_number'=>'BREBO-008'],['id'=>9,'display_name'=>'Medewerker <X>','personnel_number'=>'BREBO-009']]
);
if (!str_contains($html,'Test &amp; &lt;Project&gt;')) throw new RuntimeException('Project name not escaped');
if (!str_contains($html,'Medewerker &lt;X&gt;')) throw new RuntimeException('Person name not escaped');
if (!str_contains($html,'BREBO-008') || !str_contains($html,'BREBO-009') || !str_contains($html,'(#8)') || !str_contains($html,'(#9)')) throw new RuntimeException('Namesakes not distinguishable');
if (str_contains($html,'method="post"') || str_contains($html,'type="submit"')) throw new RuntimeException('Unsafe submission enabled');
if (substr_count($html,' disabled') < 5) throw new RuntimeException('Preview fields must remain disabled');
echo "Office shift form preview test OK\n";
