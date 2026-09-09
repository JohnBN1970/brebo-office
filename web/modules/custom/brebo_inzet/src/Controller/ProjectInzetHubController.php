<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Desktop hub for project workforce configuration and control.
 */
final class ProjectInzetHubController extends ControllerBase {

  public function title(NodeInterface $node): string {
    $this->assertProject($node);
    return 'Inzet · ' . $node->label();
  }

  /** @return array<string, mixed> */
  public function overview(NodeInterface $node): array {
    $this->assertProject($node);
    $projectId = (int) $node->id();

    $sections = [
      ['Nu aan het werk', 'Wie is er nu actief op dit project.', 'brebo_inzet.live_workforce'],
      ['Klokken', 'Open de klokregistratie voor dit project.', 'brebo_inzet.mobile_clock'],
      ['Kloklocaties', 'Beheer toegestane projectlocaties voor klokregistratie.', 'brebo_inzet.project_clock_zones'],
      ['Afwijkingen', 'Controleer afwijkende of verdachte klokregistraties.', 'brebo_inzet.project_clock_deviations'],
      ['Instellingen', 'Beheer de projectinstellingen voor Inzet op desktop.', 'brebo_inzet.project_clock_settings'],
    ];

    $items = [];
    foreach ($sections as [$title, $description, $route]) {
      $items[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-project-inzet-hub__card']],
        'title' => [
          '#markup' => '<h3>' . $this->t('@title', ['@title' => $title]) . '</h3>',
        ],
        'description' => [
          '#markup' => '<p>' . $this->t('@description', ['@description' => $description]) . '</p>',
        ],
        'link' => Link::fromTextAndUrl(
          $this->t('Openen'),
          Url::fromRoute($route, ['node' => $projectId]),
        )->toRenderable(),
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-project-inzet-hub']],
      'intro' => [
        '#markup' => '<p>' . $this->t('Beheer personeelsinzet, klokregistratie, locaties, afwijkingen en mobiele instellingen vanuit BREBO Office. Deze desktopinstellingen werken onafhankelijk van publicatie van de mobiele app.') . '</p>',
      ],
      'items' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-project-inzet-hub__grid']],
      ] + $items,
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['node:' . $projectId],
      ],
    ];
  }

  private function assertProject(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
  }

}
