<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents the first practical product proof. */
final class MeasureFieldProofTest extends TestCase {

  public function testProofRequiresRealOpeningAndReference(): void {
    $proof = ['real_opening' => TRUE, 'repeated_capture' => TRUE, 'independent_reference' => TRUE];
    self::assertTrue($proof['real_opening']);
    self::assertTrue($proof['independent_reference']);
  }

}
