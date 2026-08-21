<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_inzet\Unit;

use Drupal\brebo_inzet\Form\MobileClockForm;
use Drupal\brebo_inzet\Service\ClockSessionManager;
use Drupal\brebo_inzet\Service\PausePolicy;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use PHPUnit\Framework\TestCase;

final class MobileClockFormDependencyTest extends TestCase {

  public function testCreateInjectsEntityTypeManager(): void {
    $container = new ContainerBuilder();
    $container->set('brebo_inzet.pause_policy', $this->createMock(PausePolicy::class));
    $container->set('brebo_inzet.clock_session_manager', $this->createMock(ClockSessionManager::class));
    $container->set('entity_type.manager', $this->createMock(EntityTypeManagerInterface::class));

    self::assertInstanceOf(MobileClockForm::class, MobileClockForm::create($container));
  }

}
