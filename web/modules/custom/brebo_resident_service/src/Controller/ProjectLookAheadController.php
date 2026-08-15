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
      $label = strtoupper($signal);
      $packageLink = Link::fromTextAndUrl((string) $item['package'], Url::fromRoute('entity.node.canonical', ['node' => (int) $item['package_id']]))->toRenderable();
      $accessLink = Link::fromTextAndUrl($this->t('Toegang bekijken'), Url::fromRoute('brebo_resident_service.work_package_access', ['node' => (int) $item['package_id']]))->toRenderable();
      $rows[] = [
        $label,
        ['data' => $packageLink],
        $item['planned_start'],
        $this->t('@n dagen', ['@n' => $item['days_until_start']]),
        $item['percentage'] === NULL ? '—' : $item['percentage'] . '%',
        (int) $item['attention'],
        $item['reason'],
        ['data' => $accessLink],
      ];
    }

    return [
      'intro' => ['#markup' => '<p>Vooruitblik 42 dagen. Groen = toegang gereed/n.v.t.; oranje = nog niet gereed; rood = nog niet gereed en start binnen 7 dagen. Signalering wijzigt de planning of formele vrijgave niet automatisch.</p>'],
      'summary' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Rood: @n', ['@n' => $counts['rood']]),
          $this->t('Oranje: @n', ['@n' => $counts['oranje']]),
          $this->t('Groen: @n', ['@n' => $counts['groen']]),
        ],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => ['Signaal', 'Werkpakket', 'Geplande start', 'Tot start', 'Startgereed', 'Aandacht', 'Reden', 'Detail'],
        '#rows' => $rows,
        '#empty' => $this->t('Geen geplande werkpakketten binnen de komende 42 dagen.'),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }
}
