<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyExportValidator.php';
require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyImportRepository.php';
require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyImportCommand.php';

exit(\Brebo\Office\Intake\WebsiteCrmLegacyImportCommand::run($argv));
