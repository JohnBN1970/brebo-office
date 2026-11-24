<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcProjectLaborTotal.php';
use Brebo\Office\Calculation\OfficeCalcProjectLaborTotal;
$check=static function(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);};
$r=OfficeCalcProjectLaborTotal::summarize([
 ['cost_type'=>'labor','labor_hours'=>'288.0000'],
 ['cost_type'=>'labor','labor_hours'=>'103.6800'],
 ['cost_type'=>'material','labor_hours'=>null],
 ['cost_type'=>'material','cost_components'=>['labor'=>'50.0000'],'labor_hours'=>'77.7600'],
]);
$check($r['hours']==='469.4400'&&$r['complete']&&$r['labor_lines']===3,'Projecttotaal klopt niet');
$r=OfficeCalcProjectLaborTotal::summarize([['cost_type'=>'labor','labor_hours'=>null]]);
$check(!$r['complete']&&$r['unknown_lines']===1&&$r['hours']==='0.0000','Ontbrekende uren niet gemarkeerd');
echo "PROJECT_LABOR_TOTAL_OK\n";
