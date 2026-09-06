<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the first semantic geometry keys used by field capture. */
final class MeasureObservationVocabularyTest extends TestCase {

  public function testCoreOpeningKeysAreExplicit(): void {
    $keys = [
      'opening.width.top',
      'opening.width.middle',
      'opening.width.bottom',
      'opening.height.left',
      'opening.height.middle',
      'opening.height.right',
      'opening.kalf.height',
    ];
    self::assertCount(7, $keys);
    self::assertContains('opening.kalf.height', $keys);
  }

}
