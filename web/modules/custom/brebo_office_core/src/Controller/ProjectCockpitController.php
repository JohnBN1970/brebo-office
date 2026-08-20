<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Central steering cockpit for one BREBO project.
 */
final class ProjectCockpitController extends ControllerBase {

  public function title(NodeInterface $node): string {
    $this->assertProject($node);
    return (string) $node->label();
  }

  public function overview(NodeInterface $node): array {
    $this->assertProject($node);
    $projectId = (int) $node->id();

    $cards = [
      $this->card('Planning', $this->countProjectNodes('brebo_planning_activity', $projectId), 'activiteiten', 'brebo_office_core.project_planning', ['node' => $projectId]),
      $this->card('Inzet', $this->countProjectNodes('brebo_clock_registration', $projectId, ['field_brebo_clock_status' => 'Open']), 'nu actief', 'brebo_inzet.live_workforce', ['node' => $projectId]),
      $this->card('Klokafwijkingen', $this->countClockDeviations($projectId), 'aandachtspunten', 'brebo_inzet.project_clock_deviations', ['node' => $projectId]),
      $this->card('Werkpakketten', $this->countProjectNodes('brebo_work_package', $projectId), 'werkpakketten', 'brebo_office_core.work_packages'),
      $this->card('Inkoop', $this->countProjectNodes('brebo_rfq', $projectId), 'prijsaanvragen', 'brebo_office_core.rfqs'),
      $this->card('Risico’s', $this->countProjectNodes('brebo_risk', $projectId), 'geregistreerd', 'brebo_office_core.risks'),
      $this->card('Acties', $this->countProjectNodes('brebo_action', $projectId), 'projectacties', 'brebo_office_core.actions'),
      $this->card('Financiën', NULL, 'projectfinanciën openen', 'brebo_finance.project_finance_page', ['project_nid' => $projectId]),
    ];

    $rows = [];
    foreach ($cards as $card) {
      $rows[] = [
        ['data' => ['#markup' => '<strong>' . $card['title'] . '</strong>']],
        $card['value'] === NULL ? '—' : (string) $card['value'],
        $card['subtitle'],
        ['data' => $card['link']],
      ];
    }

    return [
      'heading' => [
        '#markup' => '<div class="brebo-project-cockpit__intro"><strong>Projectcockpit</strong><br>Één plek voor de actuele projectsturing. Modules leveren de data; Project brengt het besluitbeeld samen.</div>',
      ],
      'quick_actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'planning' => $this->linkButton('Planning', 'brebo_office_core.project_planning', ['node' => $projectId]),
        'clock' => $this->linkButton('Klokken', 'brebo_inzet.mobile_clock', ['node' => $projectId]),
        'workforce' => $this->linkButton('Nu aan het werk', 'brebo_inzet.live_workforce', ['node' => $projectId]),
        'finance' => $this->linkButton('Financiën', 'brebo_finance.project_finance_page', ['project_nid' => $projectId]),
        'edit' => $this->linkButton('Project bewerken', 'entity.node.edit_form', ['node' => $projectId]),
      ],
      'steering' => [
        '#type' => 'table',
        '#header' => [$this->t('Onderdeel'), $this->t('Aantal'), $this->t('Status'), $this->t('Openen')],
        '#rows' => $rows,
        '#empty' => $this->t('Nog geen projectdata beschikbaar.'),
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['node:' . $projectId, 'node_list'],
        'max-age' => 0,
      ],
    ];
  }

  /** @return array{title:string,value:?int,subtitle:string,link:array} */
  private function card(string $title, ?int $value, string $subtitle, string $route, array $parameters = []): array {
    return [
      'title' => $title,
      'value' => $value,
      'subtitle' => $subtitle,
      'link' => Link::fromTextAndUrl($this->t('Openen'), Url::fromRoute($route, $parameters))->toRenderable(),
    ];
  }

  private function linkButton(string $label, string $route, array $parameters = []): array {
    return [
      '#type' => 'link',
      '#title' => $this->t($label),
      '#url' => Url::fromRoute($route, $parameters),
      '#attributes' => ['class' => ['button']],
    ];
  }

  private function countProjectNodes(string $bundle, int $projectId, array $conditions = []): int {
    $nodeType = $this->entityTypeManager()->getStorage('node_type')->load($bundle);
    if ($nodeType === NULL) {
      return 0;
    }
    $fieldDefinitions = $this->entityFieldManager()->getFieldDefinitions('node', $bundle);
    if (!isset($fieldDefinitions['field_brebo_project_ref'])) {
      return 0;
    }
    $query = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->condition('field_brebo_project_ref', $projectId)
      ->count();
    foreach ($conditions as $field => $value) {
      if (isset($fieldDefinitions[$field])) {
        $query->condition($field, $value);
      }
    }
    return (int) $query->execute();
  }

  private function countClockDeviations(int $projectId): int {
    $bundle = 'brebo_clock_registration';
    $nodeType = $this->entityTypeManager()->getStorage('node_type')->load($bundle);
    if ($nodeType === NULL) {
      return 0;
    }
    $fields = $this->entityFieldManager()->getFieldDefinitions('node', $bundle);
    if (!isset($fields['field_brebo_project_ref'], $fields['field_brebo_clock_severity'])) {
      return 0;
    }
    return (int) $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->condition('field_brebo_project_ref', $projectId)
      ->condition('field_brebo_clock_severity', ['oranje', 'rood'], 'IN')
      ->count()
      ->execute();
  }

  private function assertProject(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
  }

}
