<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Manages the durable workforce membership of a project.
 */
final class ProjectTeamForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  public function getFormId(): string {
    return 'brebo_inzet_project_team';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }

    $team = [];
    if ($node->hasField('field_brebo_project_team') && !$node->get('field_brebo_project_team')->isEmpty()) {
      foreach ($node->get('field_brebo_project_team')->referencedEntities() as $account) {
        if ($account instanceof UserInterface) {
          $team[] = $account;
        }
      }
    }

    $form['context'] = [
      '#markup' => '<div class="brebo-page-header__main"><p class="brebo-page-header__eyebrow">BREBO INZET</p><h2>Projectteam</h2><p class="brebo-page-header__description">Koppel hier de mensen die structureel bij dit project horen. Dagplanning bepaalt wanneer iemand wordt verwacht; deze koppeling bepaalt op welke projecten Inzet en OnSite de medewerker herkennen.</p></div>',
    ];

    $form['team'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Medewerkers op dit project'),
      '#target_type' => 'user',
      '#tags' => TRUE,
      '#default_value' => $team,
      '#description' => $this->t('Zoek op naam en voeg één of meer medewerkers toe. Verwijderen uit dit veld haalt alleen de projectkoppeling weg; historische planning en klokregistraties blijven bestaan.'),
      '#required' => FALSE,
    ];

    if ($team !== []) {
      $rows = [];
      foreach ($team as $account) {
        $employeeNumber = $account->hasField('field_brebo_employee_number')
          ? (string) ($account->get('field_brebo_employee_number')->value ?? '')
          : '';
        $jobTitle = $account->hasField('field_brebo_job_title')
          ? (string) ($account->get('field_brebo_job_title')->value ?? '')
          : '';
        $rows[] = [
          $account->getDisplayName(),
          $employeeNumber !== '' ? $employeeNumber : '-',
          $jobTitle !== '' ? $jobTitle : '-',
        ];
      }
      $form['current'] = [
        '#type' => 'table',
        '#caption' => $this->t('Huidig projectteam'),
        '#header' => [$this->t('Medewerker'), $this->t('Personeelsnummer'), $this->t('Functie')],
        '#rows' => $rows,
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Projectteam opslaan'),
      '#button_type' => 'primary',
    ];

    $form_state->set('project_id', (int) $node->id());
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $project = $this->entityTypeManager->getStorage('node')->load((int) $form_state->get('project_id'));
    if (!$project instanceof NodeInterface || $project->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }

    $ids = [];
    foreach ((array) $form_state->getValue('team') as $item) {
      $targetId = (int) ($item['target_id'] ?? 0);
      if ($targetId > 0) {
        $ids[$targetId] = ['target_id' => $targetId];
      }
    }

    $project->set('field_brebo_project_team', array_values($ids));
    $project->setNewRevision(TRUE);
    $project->setRevisionLogMessage('Projectteam bijgewerkt via BREBO Inzet.');
    $project->save();

    $this->messenger()->addStatus($this->formatPlural(
      count($ids),
      'Projectteam opgeslagen met 1 medewerker.',
      'Projectteam opgeslagen met @count medewerkers.'
    ));
  }

}
