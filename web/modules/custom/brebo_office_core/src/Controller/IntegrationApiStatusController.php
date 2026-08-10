<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\brebo_office_core\Service\IntegrationApiClient;
use Drupal\brebo_office_core\Service\IntegrationApiClientInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows the read-only central Integration API POC status.
 */
final class IntegrationApiStatusController extends ControllerBase {

  public function __construct(
    private readonly IntegrationApiClientInterface $client,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(new IntegrationApiClient(
      $container->get('http_client'),
      $container->get('logger.factory')->get('brebo_office_core'),
    ));
  }

  public function status(): array {
    $result = $this->client->status();
    $labels = [
      'healthy' => $this->t('Bereikbaar'),
      'degraded' => $this->t('Bereikbaar, status niet gezond'),
      'unreachable' => $this->t('Niet bereikbaar'),
      'not_configured' => $this->t('Niet geconfigureerd'),
    ];

    return [
      'notice' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--status']],
        'text' => [
          '#markup' => $this->t('Read-only POC: deze controle verzendt geen communicatie-, project- of persoonsgegevens.'),
        ],
      ],
      'status' => [
        '#type' => 'table',
        '#header' => [$this->t('Controle'), $this->t('Waarde')],
        '#rows' => [
          [$this->t('Integration API'), $labels[$result['state']] ?? $result['state']],
          [$this->t('HTTP-status'), $result['http_status'] ?? '—'],
          [$this->t('Responstijd'), $result['response_time_ms'] === NULL ? '—' : $result['response_time_ms'] . ' ms'],
          [$this->t('Gecontroleerd op'), $result['checked_at']],
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
