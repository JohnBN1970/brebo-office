<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\brebo_office_core\Service\WorkforceTimeEntryControl;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the BREBO Inzet time approval cockpit.
 */
final class WorkforceTimeApprovalController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly WorkforceTimeEntryControl $control,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('brebo_office_core.workforce_time_entry_control'),
    );
  }

  public function overview(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_time_entry')
      ->condition('field_brebo_time_status', ['Concept', 'Ingediend', 'Gecorrigeerd'], 'IN')
      ->sort('field_brebo_time_start', 'ASC')
      ->execute();

    $rows = [];
    $counts = ['Akkoord' => 0, 'Afwijking' => 0, 'Blokkade' => 0];
    foreach ($storage->loadMultiple($ids) as $entry) {
      if (!$entry instanceof NodeInterface) {
        continue;
      }
      $assessment = $this->assessEntry($entry);
      $counts[$assessment['status']]++;

      $shift = $entry->get('field_brebo_time_shift')->entity;
      $person = $entry->get('field_brebo_time_contact')->entity ?? $entry->get('field_brebo_time_user')->entity;
      $budget = $entry->get('field_brebo_time_budget')->entity;
      $issues = array_merge($assessment['blocking'], $assessment['deviations']);

      $rows[] = [
        'data' => [
          $assessment['status'],
          $person?->label() ?? '—',
          $shift?->label() ?? '—',
          number_format($assessment['planned_hours'], 2, ',', '.'),
          number_format($assessment['actual_hours'], 2, ',', '.'),
          number_format($assessment['delta_hours'], 2, ',', '.'),
          $budget?->label() ?? '—',
          $issues ? implode(' ', $issues) : (string) $this->t('Geen afwijkingen.'),
          Link::fromTextAndUrl($this->t('Beoordelen'), Url::fromRoute('brebo_office_core.inzet_time_review', ['node' => $entry->id()]))->toRenderable(),
        ],
        'class' => ['brebo-inzet-time-' . strtolower($assessment['status'])],
      ];
    }

    return [
      'summary' => [
        '#type' => 'table',
        '#attributes' => ['class' => ['brebo-calc-summary']],
        '#header' => [$this->t('Te beoordelen'), $this->t('Akkoord'), $this->t('Afwijking'), $this->t('Blokkade')],
        '#rows' => [[count($rows), $counts['Akkoord'], $counts['Afwijking'], $counts['Blokkade']]],
      ],
      'explanation' => [
        '#markup' => '<p>' . $this->t('BREBO Office controleert iedere urenregistratie opnieuw op klokbewijs, planning, locatie en vrijgegeven werkbegrotingsuren. Blokkades kunnen niet worden goedgekeurd voordat de oorzaak is opgelost.') . '</p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Controle'),
          $this->t('Persoon'),
          $this->t('Dienst'),
          $this->t('Gepland'),
          $this->t('Werkelijk'),
          $this->t('Verschil'),
          $this->t('Werkbegroting'),
          $this->t('Signalen'),
          $this->t('Actie'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Geen urenregistraties wachten op beoordeling.'),
        '#sticky' => TRUE,
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['node_list:brebo_time_entry', 'node_list:brebo_clock_event', 'node_list:brebo_shift', 'node_list:brebo_work_budget_line'],
        'max-age' => 60,
      ],
    ];
  }

  /**
   * @return array{status:string,deviations:string[],blocking:string[],planned_hours:float,actual_hours:float,delta_hours:float}
   */
  private function assessEntry(NodeInterface $entry): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $shift = $entry->get('field_brebo_time_shift')->entity;
    $clockTypes = [];
    $geoStatuses = [];
    if ($shift instanceof NodeInterface) {
      $clockIds = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'brebo_clock_event')
        ->condition('field_brebo_clock_shift', $shift->id())
        ->execute();
      foreach ($storage->loadMultiple($clockIds) as $clock) {
        if (!$clock instanceof NodeInterface) {
          continue;
        }
        $clockTypes[] = (string) $clock->get('field_brebo_clock_type')->value;
        $geoStatuses[] = (string) $clock->get('field_brebo_clock_geo_status')->value;
      }
    }

    $budget = $entry->get('field_brebo_time_budget')->entity;
    $budgetHours = $budget instanceof NodeInterface && $budget->hasField('field_brebo_budget_hours')
      ? (float) ($budget->get('field_brebo_budget_hours')->value ?? 0)
      : 0.0;
    $approvedHours = 0.0;
    if ($budget instanceof NodeInterface) {
      $approvedIds = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'brebo_time_entry')
        ->condition('field_brebo_time_budget', $budget->id())
        ->condition('field_brebo_time_status', 'Goedgekeurd')
        ->condition('nid', $entry->id(), '<>')
        ->execute();
      foreach ($storage->loadMultiple($approvedIds) as $approved) {
        if ($approved instanceof NodeInterface) {
          $approvedHours += (float) ($approved->get('field_brebo_time_hours')->value ?? 0);
        }
      }
    }

    return $this->control->assess([
      'planned_start' => $shift instanceof NodeInterface ? $shift->get('field_brebo_shift_start')->value : NULL,
      'planned_end' => $shift instanceof NodeInterface ? $shift->get('field_brebo_shift_end')->value : NULL,
      'actual_start' => $entry->get('field_brebo_time_start')->value,
      'actual_end' => $entry->get('field_brebo_time_end')->value,
      'break_minutes' => (int) ($entry->get('field_brebo_time_break_min')->value ?? 0),
      'actual_hours' => (float) ($entry->get('field_brebo_time_hours')->value ?? 0),
      'clock_types' => $clockTypes,
      'geo_statuses' => $geoStatuses,
      'budget_hours' => $budgetHours,
      'approved_budget_hours' => $approvedHours,
      'tolerance_minutes' => 15,
    ]);
  }

}
