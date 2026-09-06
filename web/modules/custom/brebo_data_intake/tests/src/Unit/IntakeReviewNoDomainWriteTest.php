<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Guards the workbench from coupling to a specific business module. */
final class IntakeReviewNoDomainWriteTest extends UnitTestCase {

  public function testWorkbenchHasNoFinanceOrProjectServiceDependency(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('brebo_finance', $source);
    self::assertStringNotContainsString('brebo_project.', $source);
    self::assertStringNotContainsString('entity_type.manager', $source);
  }

}
