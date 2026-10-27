<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OnSiteHoursPresentation.php';
use Brebo\Office\Inzet\OnSiteHoursPresentation as View;
foreach(['1,5'=>5400,'1.5'=>5400,'0,25'=>900,'24'=>86400,''=>null]as$input=>$expected)if(View::correction((string)$input)!==$expected)throw new RuntimeException('Hour conversion failed');
foreach(['24,01','1,005','-1','1e2','1:30']as$input){$denied=false;try{View::correction($input);}catch(InvalidArgumentException){$denied=true;}if(!$denied)throw new RuntimeException('Invalid correction accepted');}
if(View::duration(28800)!=='8 uur'||View::duration(5400)!=='1 uur 30 min'||View::duration(0)!=='0 min')throw new RuntimeException('Duration display failed');
if(View::moment('2026-10-24T07:00:00Z')!=='24-10-2026 09:00'||View::moment('2026-10-26T07:00:00Z')!=='26-10-2026 08:00')throw new RuntimeException('Dutch summer/winter time failed');
echo "Hours, decimal corrections and Dutch local timestamps passed\n";
