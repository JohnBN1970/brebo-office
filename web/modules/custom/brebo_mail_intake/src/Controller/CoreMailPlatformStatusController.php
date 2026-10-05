<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Controller;

use Brebo\Mail\Service\ProvisioningService;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class CoreMailPlatformStatusController extends ControllerBase {

  public function __construct(private readonly ProvisioningService $provisioning) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_mail_intake.core_provisioning'));
  }

  public function page(): array {
    $health = $this->provisioning->gatewayHealth();
    $rows = [];
    foreach ($this->provisioning->recentJobs(50) as $job) {
      $rows[] = [
        (string) $job['id'],
        (string) $job['resource_type'] . ' #' . (string) $job['resource_id'],
        (string) $job['operation'],
        (string) $job['status'],
        (string) $job['provider_reference'],
        (string) $job['error_message'],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-mail-platform-status']],
      'header' => [
        '#markup' => '<div class="brebo-mail-workspace-header"><div><span class="brebo-mail-workspace-header__eyebrow">Mailbeheer</span><h1>Platformstatus</h1></div><div class="brebo-mail-workspace-header__state">' . htmlspecialchars((string) $health['provider'], ENT_QUOTES, 'UTF-8') . '</div></div>',
      ],
      'health' => [
        '#markup' => '<p><strong>Gateway:</strong> ' . htmlspecialchars((string) $health['message'], ENT_QUOTES, 'UTF-8') . '</p>',
      ],
      'jobs' => [
        '#type' => 'table',
        '#header' => ['Job', 'Resource', 'Actie', 'Status', 'Providerreferentie', 'Fout'],
        '#rows' => $rows,
        '#empty' => $this->t('Nog geen provisioning-jobs.'),
      ],
    ];
  }
}
