<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Controller;

use Drupal\brebo_project_cockpit\Service\ProjectMilestoneBuilder;
use Drupal\brebo_project_cockpit\Service\ProjectStatusAggregator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Project-scoped shortcomings and completion pages. */
final class ProjectSectionController extends ControllerBase {

  public function __construct(
    private readonly ProjectStatusAggregator $statusAggregator,
    private readonly ProjectMilestoneBuilder $milestoneBuilder,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_project_cockpit.project_status_aggregator'),
      $container->get('brebo_project_cockpit.project_milestone_builder'),
    );
  }

  public function qualityTitle(NodeInterface $node): string {
    $this->assertProject($node);
    return 'Tekortkomingen · ' . $node->label();
  }

  /** @return array<string, mixed> */
  public function quality(NodeInterface $node): array {
    $this->assertProject($node);
    $projectId = (int) $node->id();
    $status = $this->statusAggregator->build($projectId);
    $quality = is_array($status['domains']['quality'] ?? NULL) ? $status['domains']['quality'] : [];

    $rows = [];
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_deviation')
      ->condition('field_brebo_project_ref', $projectId)
      ->sort('changed', 'DESC')
      ->range(0, 50)
      ->execute();

    foreach ($storage->loadMultiple($ids) as $deviation) {
      $state = '—';
      foreach (['field_brebo_deviation_status', 'field_brebo_status'] as $field) {
        if ($deviation->hasField($field) && !$deviation->get($field)->isEmpty()) {
          $state = (string) $deviation->get($field)->value;
          break;
        }
      }
      $rows[] = [
        Link::fromTextAndUrl((string) $deviation->label(), $deviation->toUrl())->toString(),
        $state,
        date('d-m-Y H:i', (int) $deviation->getChangedTime()),
      ];
    }

    return [
      'summary' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-project-section-summary']],
        'status' => ['#markup' => '<strong>' . $this->t('Status') . ':</strong> ' . $this->t('@status', ['@status' => (string) ($quality['status'] ?? 'grijs')])],
        'message' => ['#markup' => '<div>' . $this->t('@message', ['@message' => (string) ($quality['message'] ?? 'Nog geen tekortkomingsinformatie.')]) . '</div>'],
      ],
      'deviations' => [
        '#type' => 'table',
        '#caption' => $this->t('Tekortkomingen'),
        '#header' => [$this->t('Tekortkoming'), $this->t('Status'), $this->t('Laatst gewijzigd')],
        '#rows' => $rows,
        '#empty' => $this->t('Geen tekortkomingen voor dit project.'),
      ],
      'all' => [
        '#type' => 'link',
        '#title' => $this->t('Alle tekortkomingen'),
        '#url' => Url::fromRoute('brebo_office_core.deviations'),
        '#attributes' => ['class' => ['button']],
      ],
      '#cache' => ['tags' => ['node:' . $projectId, 'node_list'], 'contexts' => ['user.permissions']],
    ];
  }

  public function completionTitle(NodeInterface $node): string {
    $this->assertProject($node);
    return 'Oplevering · ' . $node->label();
  }

  /** @return array<string, mixed> */
  public function completion(NodeInterface $node): array {
    $this->assertProject($node);
    $projectId = (int) $node->id();
    $milestones = $this->milestoneBuilder->build($projectId);
    $next = is_array($milestones['next_milestone'] ?? NULL) ? $milestones['next_milestone'] : [];

    $items = [];
    foreach ((array) ($milestones['milestones'] ?? []) as $milestone) {
      if (!is_array($milestone)) {
        continue;
      }
      $items[] = [
        '#markup' => '<strong>' . $this->t('@label', ['@label' => (string) ($milestone['label'] ?? 'Mijlpaal')]) . '</strong>'
          . (!empty($milestone['due']) ? ' · ' . $this->t('@due', ['@due' => (string) $milestone['due']]) : ''),
      ];
    }

    return [
      'phase' => [
        '#markup' => '<p><strong>' . $this->t('Fase:') . '</strong> ' . $this->t('@phase', ['@phase' => (string) ($milestones['current_phase'] ?? '—')]) . '</p>',
      ],
      'next' => [
        '#markup' => '<p><strong>' . $this->t('Volgende mijlpaal:') . '</strong> ' . $this->t('@next', ['@next' => (string) ($next['label'] ?? '—')]) . '</p>',
      ],
      'milestones' => [
        '#theme' => 'item_list',
        '#title' => $this->t('Mijlpalen oplevering'),
        '#items' => $items,
        '#empty' => $this->t('Nog geen mijlpalen voor de oplevering beschikbaar.'),
      ],
      'explanation' => [
        '#type' => 'details',
        '#title' => $this->t('Toelichting'),
        '#open' => FALSE,
        'text' => ['#markup' => '<p>' . $this->t('De oplevering volgt de planning en de vastgelegde projectmijlpalen. Restpunten en tekortkomingen worden in het projectdossier apart bijgehouden.') . '</p>'],
      ],
      '#cache' => ['tags' => ['node:' . $projectId, 'node_list'], 'contexts' => ['user.permissions']],
    ];
  }

  private function assertProject(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
  }

}
