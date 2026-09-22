<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Maintains simple employee unavailability periods for planning. */
final class WorkforceAvailabilityForm extends FormBase {

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  public function getFormId(): string { return 'brebo_inzet_workforce_availability'; }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $users = [];
    foreach ($this->entityTypeManager->getStorage('user')->loadByProperties(['status' => 1]) as $account) {
      if ((int) $account->id() > 0) {
        $users[(int) $account->id()] = $account->getDisplayName();
      }
    }
    asort($users, SORT_NATURAL | SORT_FLAG_CASE);

    $form['person'] = ['#type' => 'select', '#title' => $this->t('Medewerker'), '#options' => $users, '#required' => TRUE];
    $form['type'] = ['#type' => 'select', '#title' => $this->t('Reden'), '#options' => ['leave' => $this->t('Verlof'), 'unavailable' => $this->t('Niet beschikbaar')], '#required' => TRUE];
    $form['period'] = ['#type' => 'container', '#attributes' => ['class' => ['container-inline']]];
    $form['period']['start'] = ['#type' => 'date', '#title' => $this->t('Van'), '#required' => TRUE];
    $form['period']['end'] = ['#type' => 'date', '#title' => $this->t('Tot en met'), '#required' => TRUE];
    $form['note'] = ['#type' => 'textfield', '#title' => $this->t('Notitie'), '#maxlength' => 255];
    $form['actions'] = ['#type' => 'actions', 'submit' => ['#type' => 'submit', '#value' => $this->t('Niet-beschikbaarheid vastleggen'), '#button_type' => 'primary']];

    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', 'brebo_workforce_availability')->sort('field_brebo_unavailable_start', 'ASC')->range(0, 100)->execute();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $item) {
      $person = $item->get('field_brebo_plan_user')->entity;
      $rows[] = [
        $person?->label() ?? '—',
        ((string) $item->get('field_brebo_unavailable_type')->value === 'leave') ? $this->t('Verlof') : $this->t('Niet beschikbaar'),
        (string) $item->get('field_brebo_unavailable_start')->value,
        (string) $item->get('field_brebo_unavailable_end')->value,
        (string) $item->get('field_brebo_unavailable_note')->value,
      ];
    }
    $form['current'] = ['#type' => 'table', '#caption' => $this->t('Vastgelegde niet-beschikbaarheid'), '#header' => [$this->t('Medewerker'), $this->t('Reden'), $this->t('Van'), $this->t('Tot'), $this->t('Notitie')], '#rows' => $rows, '#empty' => $this->t('Nog geen niet-beschikbaarheid vastgelegd.')];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $start = (string) $form_state->getValue(['period', 'start']);
    $end = (string) $form_state->getValue(['period', 'end']);
    if ($end < $start) {
      $form_state->setErrorByName('period][end', $this->t('Einddatum moet op of na de startdatum liggen.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = (int) $form_state->getValue('person');
    $start = (string) $form_state->getValue(['period', 'start']);
    $end = (string) $form_state->getValue(['period', 'end']);
    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'brebo_workforce_availability',
      'title' => sprintf('%s - %s - %s', $account?->label() ?? ('Gebruiker ' . $uid), $start, $end),
      'status' => 1,
      'field_brebo_plan_user' => ['target_id' => $uid],
      'field_brebo_unavailable_type' => (string) $form_state->getValue('type'),
      'field_brebo_unavailable_start' => $start,
      'field_brebo_unavailable_end' => $end,
      'field_brebo_unavailable_note' => (string) $form_state->getValue('note'),
    ]);
    $node->save();
    $this->messenger()->addStatus($this->t('Niet-beschikbaarheid vastgelegd.'));
  }
}
