<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_glass\Unit\Service;

use Drupal\brebo_glass\Service\GlassReferenceCaseValidator;
use PHPUnit\Framework\TestCase;

final class GlassReferenceCaseValidatorTest extends TestCase {

  public function testUnverifiedReferenceFailsClosed(): void {
    $result = (new GlassReferenceCaseValidator())->compare(
      ['input_snapshot' => ['width_mm' => 1000.0], 'results' => []],
      ['reference_id' => 'R1', 'reference_source' => 'external', 'reference_version' => '1', 'input' => ['width_mm' => 1000.0], 'results' => [], 'verified' => FALSE],
    );
    self::assertSame('blocked', $result['status']);
    self::assertFalse($result['production_release']);
  }

  public function testMatchingVerifiedCaseProducesDifferencesButNoRelease(): void {
    $input = ['width_mm' => 1000.0, 'height_mm' => 1500.0];
    $result = (new GlassReferenceCaseValidator())->compare(
      [
        'input_snapshot' => $input,
        'results' => ['stress_mpa' => 12.1, 'deflection_mm' => 4.2, 'utilisation_ratio' => 0.61],
      ],
      [
        'reference_id' => 'R2', 'reference_source' => 'independent_reference', 'reference_version' => '1',
        'input' => $input, 'verified' => TRUE,
        'results' => ['stress_mpa' => 12.0, 'deflection_mm' => 4.0, 'utilisation_ratio' => 0.60],
      ],
    );
    self::assertSame('compared', $result['status']);
    self::assertArrayHasKey('stress_mpa', $result['comparisons']);
    self::assertFalse($result['validated']);
    self::assertFalse($result['production_release']);
  }

  public function testInputMismatchFailsClosed(): void {
    $result = (new GlassReferenceCaseValidator())->compare(
      ['input_snapshot' => ['width_mm' => 1000.0], 'results' => []],
      ['reference_id' => 'R3', 'reference_source' => 'external', 'reference_version' => '1', 'input' => ['width_mm' => 900.0], 'results' => [], 'verified' => TRUE],
    );
    self::assertSame('blocked', $result['status']);
    self::assertSame('input_mismatch', $result['gate']);
  }

}
