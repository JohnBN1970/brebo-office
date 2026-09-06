<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the queue uses Drupal's accessible table render element. */
final class IntakeReviewTableTest extends UnitTestCase {

  public function testControllerUsesTableRenderElement(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString("'#type' => 'table'", $source);
    self::assertStringContainsString("'#empty' =>", $source);
  }

}
