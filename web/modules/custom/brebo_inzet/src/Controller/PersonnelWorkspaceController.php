<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Controller;

use Drupal\brebo_inzet\Service\PersonnelAssignmentComparison;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Company-wide Personeel worklists. */
final class PersonnelWorkspaceController extends ControllerBase {

  public function __construct(private readonly PersonnelAssignmentComparison $comparison) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_inzet.personnel_assignment_comparison'));
  }

  public function hours(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(TRUE)
      ->condition('type', 'brebo_personnel_assignment')
      ->condition('field_brebo_assignment_status', 'cancelled', '<>')
      ->sort('field_brebo_plan_date', 'DESC')
      ->pager(100)->execute();

    $rows = [];
    foreach ($storage->loadMultiple($ids) as $assignment) {
      if (!$assignment instanceof NodeInterface || !$assignment->access('view')) continue;
      $actual = $this->comparison->compare($assignment);
      $planned = (float) $actual['planned_hours'];
      $clocked = (float) $actual['clocked_hours'];
      if ($planned <= 0 && $clocked <= 0) continue;

      $state = (string) $actual['state'];
      $signal = match (TRUE) {
        in_array($state, ['clocked_without_plan', 'incomplete', 'unclocked'], TRUE) => '🔴',
        in_array($state, ['under', 'over', 'today_pending'], TRUE) => '🟠',
        $state === 'match' || ($clocked > 0 && abs((float) $actual['delta_hours']) <= .25) => '🟢',
        default => '⚪',
      };
      $project = $assignment->get('field_brebo_project_ref')->entity;
      $person = $assignment->get('field_brebo_plan_user')->entity;
      $review = (string) ($assignment->get('field_brebo_actual_status')->value ?? 'open');
      $rows[] = [
        $signal,
        $person?->label() ?? '-',
        $project?->label() ?? '-',
        (string) ($assignment->get('field_brebo_plan_date')->value ?? ''),
        number_format($planned, 2, ',', '.') . ' u',
        number_format($clocked, 2, ',', '.') . ' u',
        number_format((float) $actual['delta_hours'], 2, ',', '.') . ' u',
        match ($review) {'approved' => 'Goedgekeurd', 'worked' => 'Ingediend', default => 'Open'},
        $project instanceof NodeInterface
          ? Link::fromTextAndUrl($this->t('Open'), Url::fromRoute('brebo_inzet.project_hours_control', ['node' => $project->id()]))->toRenderable()
          : '-',
      ];
    }

    return [
      '#cache' => ['contexts' => ['user', 'url.query_args:pagers'], 'tags' => ['node_list:brebo_personnel_assignment', 'node_list:brebo_clock_registration'], 'max-age' => 60],
      'header' => ['#markup' => '<div class="brebo-page-header__main"><p class="brebo-page-header__eyebrow">BREBO PERSONEEL</p><h1>Uren</h1><p>Bedrijfsbrede urencontrole. Afwijkingen blijven zichtbaar; goedkeuring gebeurt per project.</p></div>'],
      'table' => ['#type' => 'table', '#header' => ['Status','Medewerker','Project','Datum','Gepland','Werkelijk','Verschil','Goedkeuring',''], '#rows' => $rows, '#empty' => $this->t('Geen uren om te controleren.')],
      'pager' => ['#type' => 'pager'],
    ];
  }

  public function employees(): array {
    $storage = $this->entityTypeManager()->getStorage('user');
    $ids = $storage->getQuery()->accessCheck(TRUE)
      ->condition('status', 1)
      ->sort('name')->execute();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $account) {
      if (!$account instanceof UserInterface || $account->isAnonymous()) continue;
      $status = $account->hasField('field_brebo_workforce_status') ? (string) ($account->get('field_brebo_workforce_status')->value ?? 'active') : 'active';
      $rows[] = [
        $account->getDisplayName(),
        $account->hasField('field_brebo_employee_number') ? (string) ($account->get('field_brebo_employee_number')->value ?? '') : '',
        $account->hasField('field_brebo_job_title') ? (string) ($account->get('field_brebo_job_title')->value ?? '') : '',
        $status === 'inactive' ? $this->t('Inactief') : $this->t('Actief'),
        Link::fromTextAndUrl($this->t('Open'), Url::fromRoute('entity.user.edit_form', ['user' => $account->id()]))->toRenderable(),
      ];
    }
    return [
      '#cache' => ['contexts' => ['user'], 'tags' => ['user_list'], 'max-age' => 60],
      'header' => ['#markup' => '<div class="brebo-page-header__main"><p class="brebo-page-header__eyebrow">BREBO PERSONEEL</p><h1>Medewerkers</h1><p>Personeelsnummer, functie en inzetstatus vanuit één medewerkersoverzicht.</p></div>'],
      'table' => ['#type' => 'table', '#header' => ['Medewerker','Personeelsnummer','Functie','Status',''], '#rows' => $rows, '#empty' => $this->t('Geen medewerkers gevonden.')],
    ];
  }
}
