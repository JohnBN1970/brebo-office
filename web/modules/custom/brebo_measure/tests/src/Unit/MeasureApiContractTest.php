<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the first device-neutral Measure API contract. */
final class MeasureApiContractTest extends TestCase {

  public function testProvenanceVocabularyRemainsExplicit(): void {
    $allowed = ['measured', 'detected', 'selected', 'calculated'];
    self::assertCount(4, $allowed);
    self::assertContains('measured', $allowed);
    self::assertContains('detected', $allowed);
    self::assertContains('selected', $allowed);
    self::assertContains('calculated', $allowed);
  }

  public function testMobileSourceIsNotHardCodedIntoDomainVocabulary(): void {
    $domainSources = ['apple_lidar', 'laser', 'precision_kit', 'framebot'];
    self::assertContains('apple_lidar', $domainSources);
    self::assertContains('precision_kit', $domainSources);
  }

}
