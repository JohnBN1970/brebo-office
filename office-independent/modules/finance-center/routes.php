<?php
declare(strict_types=1);
if(getenv('OFFICE_BASE_PATH')!=='/office-test'||getenv('OFFICE_FINANCE_CENTER_ENABLED')!=='enabled')return [];
return ['/office-test/finance/administratie'=>'office-finance-center.php'];
