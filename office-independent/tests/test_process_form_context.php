<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Process/OfficeProcessFormCatalog.php';
require_once dirname(__DIR__).'/src/Process/OfficeProcessFormContext.php';
use Brebo\Office\Process\OfficeProcessFormCatalog as C;
use Brebo\Office\Process\OfficeProcessFormContext as X;
foreach (C::all() as $code=>$entry) {
    $types=X::typesFor($code);
    if ($types===[]) throw new RuntimeException('Missing context: '.$code);
    foreach ($types as $type) if (!X::accepts($code,$type)) throw new RuntimeException('Broken context mapping: '.$code);
}
foreach ([['FR-201','project'],['FR-300','lead'],['FR-400','relation'],['RG-610','building'],['FR-999','project']] as [$code,$type]) {
    if (X::accepts($code,$type)) throw new RuntimeException('Cross-module context accepted');
}
echo "21 process form resource context contracts OK\n";
