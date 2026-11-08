<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/src/Governance/OfficeGovernanceSchema.php';
echo "-- Office native quality, risk and procurement. Review-only SQL.\n";
echo "-- Verify existing unsigned BIGINT project/building keys before applying.\n";
echo "-- No grants, data import, mapping or feature activation included.\n";
foreach (\Brebo\Office\Governance\OfficeGovernanceSchema::statements() as $sql) echo $sql.";\n\n";
