<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Crm/OfficeCrmAwardHistorySchema.php';
use Brebo\Office\Crm\OfficeCrmAwardHistorySchema;
$sql=file_get_contents(__DIR__.'/../migrations/20261128_crm_award_history.sql');
$expected=implode(";\n",OfficeCrmAwardHistorySchema::statements()).";";
$normalize=static fn(string $s):string=>trim(preg_replace('/\s+/', ' ',preg_replace('/^--.*$/m','',$s)));
if($normalize($sql)!==$normalize($expected))throw new RuntimeException('Award migration differs from schema');
if(preg_match('/\b(DROP|DELETE|UPDATE|TRUNCATE|ALTER|INSERT)\b/i',preg_replace('/^--.*$/m','',$sql)))throw new RuntimeException('Unexpected migration mutation');
echo "Award migration contract passed\n";
