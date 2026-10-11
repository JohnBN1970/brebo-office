<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyPartyStore.php';
require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyStageEventStore.php';
require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyRelatedImportCommand.php';

exit(\Brebo\Office\Intake\WebsiteCrmLegacyRelatedImportCommand::run($argv));
