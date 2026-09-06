<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Guards against introducing duplicate file storage in the workbench. */
final class IntakeReviewNoDuplicateStorageTest extends UnitTestCase {

  public function testWorkbenchDoesNotManageFiles(): void {
    $root = dirname(__DIR__, 3);
    $repository = file_get_contents($root . '/src/Service/IntakeReviewRepository.php');
    $controller = file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($repository);
    self::assertIsString($controller);
    self::assertStringNotContainsString('file.repository', $repository . $controller);
    self::assertStringNotContainsString('managed_file', $repository . $controller);
  }

}
