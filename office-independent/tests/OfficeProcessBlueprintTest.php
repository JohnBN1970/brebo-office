<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Intake/OfficeDisciplineCatalog.php';
require_once dirname(__DIR__) . '/src/Intake/OfficeProcessBlueprint.php';

use Brebo\Office\Intake\OfficeDisciplineCatalog as Catalog;
use Brebo\Office\Intake\OfficeProcessBlueprint as Blueprint;

foreach (Catalog::active() as $discipline) {
    $definition = Blueprint::forDiscipline($discipline);
    Blueprint::validateDefinition($definition);
    if ($definition['version'] !== 1 || $definition['discipline'] !== $discipline) {
        throw new RuntimeException('Invalid blueprint identity');
    }
}
$expertise = Blueprint::forDiscipline('expertise');
$steps = array_column($expertise['steps'], 'id');
if (!in_array('expertiserapport', $steps, true) || in_array('montage', $steps, true)) {
    throw new RuntimeException('Expertise route incorrect');
}
try {
    Blueprint::forDiscipline('mjop');
    throw new RuntimeException('MJOP should not be active');
} catch (InvalidArgumentException $expected) {
    // Expected: future discipline not activated.
}
echo "OfficeProcessBlueprintTest OK\n";
