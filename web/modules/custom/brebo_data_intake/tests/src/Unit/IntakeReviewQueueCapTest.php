<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the operator workbench starts with a manageable queue. */
final class IntakeReviewQueueCapTest extends UnitTestCase {

  public function testDefaultQueueSizeIsFifty(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringContainsString('pending(int $limit = 50)', $source);
  }

}
