<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Exposes safe GET filters for the glass schedule.
 */
final class GlassPositionFilterForm extends FormBase {

  public function getFormId(): string {
    return 'brebo_glass_position_filter_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $request = $this->getRequest();
    $form['#method'] = 'get';
    $form['#attributes']['class'][] = 'container-inline';

    $form['q'] = [
      '#type' => 'search',
      '#title' => $this->t('Zoeken'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Positie, locatie of glasopbouw'),
      '#default_value' => (string) $request->query->get('q', ''),
    ];
    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Technische status'),
      '#title_display' => 'invisible',
      '#options' => [
        '' => $this->t('- Alle statussen -'),
        'concept' => $this->t('Concept'),
        'measured' => $this->t('Ingemeten'),
        'approved' => $this->t('Technisch vrijgegeven'),
        'ordered' => $this->t('Besteld'),
        'installed' => $this->t('Gemonteerd'),
      ],
      '#default_value' => (string) $request->query->get('status', ''),
    ];
    $form['sort'] = [
      '#type' => 'hidden',
      '#value' => (string) $request->query->get('sort', 'changed'),
    ];
    $form['direction'] = [
      '#type' => 'hidden',
      '#value' => (string) $request->query->get('direction', 'desc'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['filter'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filteren'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $query = array_filter([
      'q' => trim((string) $form_state->getValue('q')),
      'status' => (string) $form_state->getValue('status'),
      'sort' => (string) $form_state->getValue('sort'),
      'direction' => (string) $form_state->getValue('direction'),
    ], static fn(string $value): bool => $value !== '');

    $form_state->setRedirect('brebo_glass.position_overview', [], ['query' => $query]);
  }

}
