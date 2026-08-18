<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_office_core\Unit;

use Drupal\brebo_office_core\Service\WorkforceQualificationMatcher;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\brebo_office_core\Service\WorkforceQualificationMatcher
 */
final class WorkforceQualificationMatcherTest extends TestCase {

  private WorkforceQualificationMatcher $matcher;

  protected function setUp(): void {
    parent::setUp();
    $this->matcher = new WorkforceQualificationMatcher();
  }

  public function testAllRequirementsAreValid(): void {
    $result = $this->matcher->match(
      ['vca' => 'VCA Basis', 'etics' => 'ETICS vakman'],
      [
        ['skill_id' => 'vca', 'status' => 'Geldig', 'expires' => '2027-01-01'],
        ['skill_id' => 'etics', 'status' => 'Geldig', 'expires' => ''],
      ],
      new \DateTimeImmutable('2026-08-18'),
    );
    self::assertSame('Passend', $result['status']);
    self::assertSame([], $result['missing']);
  }

  public function testMissingRequirementBlocks(): void {
    $result = $this->matcher->match(
      ['vca' => 'VCA Basis', 'etics' => 'ETICS vakman'],
      [['skill_id' => 'vca', 'status' => 'Geldig', 'expires' => '2027-01-01']],
      new \DateTimeImmutable('2026-08-18'),
    );
    self::assertSame('Blokkade', $result['status']);
    self::assertSame(['ETICS vakman'], $result['missing']);
  }

  public function testExpiredAndUnverifiedEvidenceBlocks(): void {
    $result = $this->matcher->match(
      ['vca' => 'VCA Basis'],
      [
        ['skill_id' => 'vca', 'status' => 'Geldig', 'expires' => '2026-08-17'],
        ['skill_id' => 'vca', 'status' => 'Te controleren', 'expires' => '2027-01-01'],
      ],
      new \DateTimeImmutable('2026-08-18'),
    );
    self::assertSame('Blokkade', $result['status']);
  }

  public function testEvidenceExpiringWithinThirtyDaysWarns(): void {
    $result = $this->matcher->match(
      ['vca' => 'VCA Basis'],
      [['skill_id' => 'vca', 'status' => 'Geldig', 'expires' => '2026-09-01']],
      new \DateTimeImmutable('2026-08-18'),
    );
    self::assertSame('Waarschuwing', $result['status']);
    self::assertSame(['VCA Basis'], $result['expiring']);
  }

  public function testNoRequirementsPasses(): void {
    $result = $this->matcher->match([], [], new \DateTimeImmutable('2026-08-18'));
    self::assertSame('Passend', $result['status']);
  }

}
