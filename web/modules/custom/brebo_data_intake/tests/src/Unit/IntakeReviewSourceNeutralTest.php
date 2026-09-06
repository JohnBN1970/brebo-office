<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Guards the workbench against becoming mail- or upload-specific. */
final class IntakeReviewSourceNeutralTest extends UnitTestCase {

  public function testRepositoryJoinsGenericSourceModel(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringContainsString("innerJoin('brebo_data_source'", $source);
    self::assertStringContainsString("addField('source', 'label', 'source_label')", $source);
    self::assertStringNotContainsString('brebo_mail', $source);
  }

}
