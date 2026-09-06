<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the first review slice exposes no destructive action. */
final class IntakeReviewNoDestructiveActionTest extends UnitTestCase {

  public function testControllerHasNoDeleteOrTrashAction(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString('Verwijderen', $source);
    self::assertStringNotContainsString('Prullenbak', $source);
  }

}
