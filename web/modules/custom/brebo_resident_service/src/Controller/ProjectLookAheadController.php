<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Controller;

use Drupal\brebo_resident_service\Service\LookAheadAccessReadiness;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Project cockpit for upcoming access-readiness risks. */
final class ProjectLookAheadController extends ControllerBase {

  public function __construct(private readonly LookAheadAccessReadiness $lookAhead) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_resident_service.look_ahead_access_readiness'));
  }

  public function title(NodeInterface $node): string {
    return $this->t('Look-ahead startgereed — @project', ['@project' => $node->label()]);
  }

  public function cockpit(NodeInterface $node): array {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }

    $items = $this->lookAhead->forProject((int) $node->id(), 42);
    $counts = ['rood' => 0, 'oranje' => 0, 'groen' => 0];
    $rows = [];
    foreach ($items as $item) {
      $signal = $item['signal'];
      $counts[$signal]++;
      $tone = ['rood' => 'critical', 'oranje' => 'attention', 'groen' => 'positive'][$signal];
      $packageLink = Link::fromTextAndUrl((string) $item['package'], Url::fromRoute('entity.node.canonical', ['node' => (int) $item['package_id']]))->toRenderable();
      $accessLink = Link::fromTextAndUrl($this->t('Bekijk'), Url::fromRoute('brebo_resident_service.work_package_access', ['node' => (int) $item['package_id']]))->toRenderable();
      $rows[] = [
        ['data' => ['#markup' => '<span class="brebo-status brebo-status--' . $tone . '">' . strtoupper($signal) . '</span>']],
        ['data' => $packageLink],
        $item['planned_start'],
        $this->t('@n dagen', ['@n' => $item['days_until_start']]),
        $item['percentage'] === NULL ? '—' : $item['percentage'] . '%',
        (int) $item['attention'],
        $item['reason'],
        ['data' => $accessLink],
      ];
    }

    $kpi = static function (string $label, int $value, string $tone): array {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-kpi', 'brebo-kpi--' . $tone]],
        'value' => ['#markup' => '<span class="brebo-kpi__value">' . $value . '</span>'],
        'label' => ['#markup' => '<span class="brebo-kpi__label">' . $label . '</span>'],
      ];
    };

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-cockpit']],
      'header' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-cockpit__header']],
        'intro' => ['#markup' => '<p class="brebo-cockpit__intro">Vooruitblik 42 dagen. Uitzonderingen staan voorop; signalering wijzigt planning of formele vrijgave niet automatisch.</p>'],
      ],
      'kpis' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-kpis']],
        'red' => $kpi('Blokkade binnen 7 dagen', $counts['rood'], 'critical'),
        'orange' => $kpi('Aandacht', $counts['oranje'], 'attention'),
        'green' => $kpi('Startgereed', $counts['groen'], 'positive'),
        'total' => $kpi('Werkpakketten in 42 dagen', count($items), 'neutral'),
      ],
      'work' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-section']],
        'heading' => ['#markup' => '<div class="brebo-section__header"><h2 class="brebo-section__title">Komende werkpakketten</h2></div>'],
        'table_wrap' => [
          '#type' => 'container', '#attributes' => ['class' => ['brebo-table-wrap']],
          'table' => [
            '#type' => 'table',
            '#header' => ['Signaal', 'Werkpakket', 'Start', 'Tot start', 'Gereed', 'Aandacht', 'Reden', 'Detail'],
            '#rows' => $rows,
            '#empty' => $this->t('Geen geplande werkpakketten binnen de komende 42 dagen.'),
          ],
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }
}
