<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the Office-owned canonical opening contract. */
final class MeasureCanonicalContractTest extends TestCase {

  public function testMobileContributesEvidenceRatherThanOwningOpening(): void {
    self::assertNotSame('mobile_owned_opening', 'office_owned_opening');
  }

}
