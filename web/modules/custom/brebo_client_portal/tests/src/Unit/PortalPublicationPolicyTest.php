<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_client_portal\Unit;

use Drupal\brebo_client_portal\Publication\PortalPublicationPolicy;
use PHPUnit\Framework\TestCase;

final class PortalPublicationPolicyTest extends TestCase {

  public function testInternalFieldsAreRemovedFromExternalProjection(): void {
    $policy = new PortalPublicationPolicy();

    $safe = $policy->sanitize('project_summary', [
      'name' => 'Testproject',
      'status' => 'In uitvoering',
      'cost_price' => '100000',
      'margin' => '25%',
      'supplier' => 'Geheim BV',
      'internal_note' => 'Niet voor klant',
      'personnel' => 'Interne inzet',
      'management_risk' => 'Intern risico',
    ]);

    self::assertSame([
      'name' => 'Testproject',
      'status' => 'In uitvoering',
    ], $safe);
  }

  public function testUnknownPublicationTypeIsRejected(): void {
    $policy = new PortalPublicationPolicy();
    $this->expectException(\InvalidArgumentException::class);
    $policy->sanitize('internal_project_entity', ['name' => 'Nooit extern']);
  }

}
