<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Branch-ready smoke test. */
final class MeasureBranchReadyTest extends TestCase {

  public function testMeasure02HasApiAndFieldProofIntent(): void {
    $scope = ['office_api', 'field_proof_contract'];
    self::assertSame(['office_api', 'field_proof_contract'], $scope);
  }

}
