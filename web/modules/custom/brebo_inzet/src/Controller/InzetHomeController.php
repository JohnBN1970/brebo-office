<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Controller;

use Drupal\brebo_inzet\Service\ClockSessionManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Fresh entry point for the BREBO Inzet app home.
 */
final class InzetHomeController extends ControllerBase {

  public function __construct(
    private readonly ClockSessionManager $clockSessionManager,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_inzet.clock_session_manager'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Shows the app home and any active clock session without redirecting.
   */
  public function start(): array {
    $userId = (int) $this->currentUser()->id();
    $open = $this->clockSessionManager->findOpenForUser($userId);
    $active = NULL;

    if ($open instanceof NodeInterface) {
      $projectId = (int) ($open->get('field_brebo_project_ref')->target_id ?? 0);
      $project = $projectId > 0 ? $this->entityTypeManager->getStorage('node')->load($projectId) : NULL;
      if ($project instanceof NodeInterface && $project->bundle() === 'brebo_project' && $project->access('view')) {
        $active = [
          '#type' => 'container',
          '#attributes' => ['class' => ['brebo-inzet-app__active']],
          'status' => [
            '#markup' => '<span class="brebo-inzet-app__active-label">' . $this->t('Je bent aanwezig op') . '</span><strong>' . htmlspecialchars((string) $project->label(), ENT_QUOTES, 'UTF-8') . '</strong>',
          ],
          'link' => Link::fromTextAndUrl(
            $this->t('NAAR ACTIEF PROJECT / VERTREK'),
            Url::fromRoute('brebo_inzet.app_clock', ['node' => $project->id()]),
          )->toRenderable(),
        ];
        $active['link']['#attributes']['class'] = ['brebo-inzet-app__active-link'];
      }
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_project')
      ->condition('status', 1)
      ->sort('title')
      ->range(0, 100)
      ->execute();

    $items = [];
    foreach ($storage->loadMultiple($ids) as $project) {
      if (!$project instanceof NodeInterface || !$project->access('view')) {
        continue;
      }
      $items[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-inzet-app__project']],
        'link' => Link::fromTextAndUrl(
          $project->label(),
          Url::fromRoute('brebo_inzet.app_clock', ['node' => $project->id()]),
        )->toRenderable(),
      ];
    }

    return [
      '#attached' => ['library' => ['brebo_inzet/pwa-shell']],
      '#cache' => ['max-age' => 0, 'contexts' => ['user']],
      'app' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-inzet-app']],
        'brand' => [
          '#markup' => '<div class="brebo-inzet-app__brand"><span class="brebo-inzet-app__mark">B</span><div><strong>BREBO Inzet</strong><span>Aanwezigheid op de bouw</span></div></div>',
        ],
        'active' => $active ?? [],
        'heading' => [
          '#markup' => '<h2>' . $this->t('Kies je project') . '</h2><p>' . $this->t('Je gaat daarna direct naar AANWEZIG / VERTREK.') . '</p>',
        ],
        'projects' => $items ?: [
          '#markup' => '<div class="brebo-inzet-app__empty">' . $this->t('Er zijn geen projecten beschikbaar waarop je kunt klokken.') . '</div>',
        ],
      ],
    ];
  }

}
