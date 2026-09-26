<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Links a standalone calculation to an Office project through a work package.
 */
final class CalculationProjectLinkForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  public function getFormId(): string {
    return 'brebo_calculation_project_link_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_calculation') {
      throw new \InvalidArgumentException('Alleen calculaties kunnen aan een project worden gekoppeld.');
    }

    $package = $node->hasField('field_brebo_package_ref') ? $node->get('field_brebo_package_ref')->entity : NULL;
    $project = $package instanceof NodeInterface && $package->hasField('field_brebo_project_ref')
      ? $package->get('field_brebo_project_ref')->entity
      : NULL;

    $form['#tree'] = TRUE;

    $form['calculation_id'] = [
      '#type' => 'hidden',
      '#value' => (int) $node->id(),
    ];

    $form['intro'] = [
      '#markup' => '<p>Koppel deze calculatie aan een bestaand Office-project. Een calculatie blijft via een werkpakket aan het project verbonden.</p>',
    ];

    $form['project'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Project'),
      '#target_type' => 'node',
      '#selection_settings' => [
        'target_bundles' => ['brebo_project'],
      ],
      '#default_value' => $project instanceof NodeInterface ? $project : NULL,
      '#required' => TRUE,
    ];

    $form['existing_package'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Bestaand werkpakket'),
      '#description' => $this->t('Optioneel. Laat leeg om voor deze calculatie een nieuw werkpakket te maken.'),
      '#target_type' => 'node',
      '#selection_settings' => [
        'target_bundles' => ['brebo_work_package'],
      ],
      '#default_value' => $package instanceof NodeInterface ? $package : NULL,
      '#required' => FALSE,
    ];

    $form['new_package'] = [
      '#type' => 'details',
      '#title' => $this->t('Nieuw werkpakket'),
      '#open' => !$package instanceof NodeInterface,
    ];

    $form['new_package']['discipline'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Discipline'),
      '#description' => $this->t('Bijvoorbeeld glas, kozijnen, schilderwerk of betonherstel. Alleen nodig bij een nieuw werkpakket.'),
      '#maxlength' => 255,
    ];

    $form['new_package']['scope'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Scope'),
      '#description' => $this->t('Korte afbakening van het werkpakket. Alleen nodig bij een nieuw werkpakket.'),
      '#rows' => 4,
      '#default_value' => 'Calculatie ' . $node->label(),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Project koppelen'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $projectId = (int) $form_state->getValue('project');
    $packageId = (int) $form_state->getValue('existing_package');

    $storage = $this->entityTypeManager->getStorage('node');
    $project = $projectId > 0 ? $storage->load($projectId) : NULL;
    if (!$project instanceof NodeInterface || $project->bundle() !== 'brebo_project') {
      $form_state->setErrorByName('project', $this->t('Kies een geldig project.'));
      return;
    }

    if ($packageId > 0) {
      $package = $storage->load($packageId);
      if (!$package instanceof NodeInterface || $package->bundle() !== 'brebo_work_package') {
        $form_state->setErrorByName('existing_package', $this->t('Kies een geldig werkpakket.'));
        return;
      }
      $packageProjectId = $package->hasField('field_brebo_project_ref') && !$package->get('field_brebo_project_ref')->isEmpty()
        ? (int) $package->get('field_brebo_project_ref')->target_id
        : 0;
      if ($packageProjectId !== $projectId) {
        $form_state->setErrorByName('existing_package', $this->t('Dit werkpakket hoort niet bij het gekozen project.'));
      }
      return;
    }

    if (trim((string) $form_state->getValue(['new_package', 'discipline'])) === '') {
      $form_state->setErrorByName('new_package][discipline', $this->t('Vul een discipline in voor het nieuwe werkpakket.'));
    }
    if (trim((string) $form_state->getValue(['new_package', 'scope'])) === '') {
      $form_state->setErrorByName('new_package][scope', $this->t('Vul een scope in voor het nieuwe werkpakket.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $calculation = $storage->load((int) $form_state->getValue('calculation_id'));
    if (!$calculation instanceof NodeInterface || $calculation->bundle() !== 'brebo_calculation') {
      throw new \RuntimeException('Calculatie niet gevonden.');
    }

    $projectId = (int) $form_state->getValue('project');
    $packageId = (int) $form_state->getValue('existing_package');

    if ($packageId === 0) {
      $code = $calculation->hasField('field_brebo_calc_code') && !$calculation->get('field_brebo_calc_code')->isEmpty()
        ? (string) $calculation->get('field_brebo_calc_code')->value
        : 'CALC-' . $calculation->id();

      $package = $storage->create([
        'type' => 'brebo_work_package',
        'title' => 'Calculatie ' . $code,
        'status' => 1,
        'field_brebo_project_ref' => ['target_id' => $projectId],
        'field_brebo_package_code' => 'CALC-' . $calculation->id(),
        'field_brebo_package_status' => 'Concept',
        'field_brebo_discipline' => trim((string) $form_state->getValue(['new_package', 'discipline'])),
        'field_brebo_package_scope' => trim((string) $form_state->getValue(['new_package', 'scope'])),
      ]);
      $package->setNewRevision(TRUE);
      $package->setRevisionLogMessage('Werkpakket automatisch aangemaakt bij projectkoppeling van calculatie ' . $calculation->label() . '.');
      $package->save();
      $packageId = (int) $package->id();
    }

    $calculation->set('field_brebo_package_ref', ['target_id' => $packageId]);
    $calculation->setNewRevision(TRUE);
    $calculation->setRevisionLogMessage('Project-/werkpakketkoppeling bijgewerkt vanuit calculatiewerkbank.');
    $calculation->save();

    $this->messenger()->addStatus($this->t('Calculatie is aan het project gekoppeld.'));
    $form_state->setRedirect('brebo_calculation.workbench', ['node' => $calculation->id()]);
  }

}
