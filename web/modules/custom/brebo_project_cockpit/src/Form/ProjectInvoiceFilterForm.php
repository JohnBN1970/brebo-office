<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/** GET-style filter and sorter for the unified project invoice register. */
final class ProjectInvoiceFilterForm extends FormBase {

  public function getFormId(): string {
    return 'brebo_project_invoice_filter';
  }

  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?NodeInterface $node = NULL,
    string $direction = 'all',
    string $state = 'all',
    string $sort = 'date_desc',
  ): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('Een BREBO-project is verplicht.');
    }
    $form_state->set('project_id', (int) $node->id());

    $form['#attributes']['class'][] = 'brebo-filter-bar';
    $form['direction'] = [
      '#type' => 'select',
      '#title' => $this->t('Soort factuur'),
      '#default_value' => $direction,
      '#options' => [
        'all' => $this->t('Alle facturen'),
        'incoming' => $this->t('Inkoopfacturen'),
        'outgoing' => $this->t('Verkoopfacturen'),
      ],
    ];
    $form['state'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#default_value' => $state,
      '#options' => [
        'all' => $this->t('Alle statussen'),
        'open' => $this->t('Openstaand'),
        'overdue' => $this->t('Vervallen'),
        'paid' => $this->t('Betaald'),
        'partial' => $this->t('Deels betaald'),
        'disputed' => $this->t('In geschil'),
      ],
    ];
    $form['sort'] = [
      '#type' => 'select',
      '#title' => $this->t('Sorteren'),
      '#default_value' => $sort,
      '#options' => [
        'date_desc' => $this->t('Nieuwste eerst'),
        'date_asc' => $this->t('Oudste eerst'),
        'due_asc' => $this->t('Vervaldatum'),
        'amount_desc' => $this->t('Bedrag hoog-laag'),
        'relation_asc' => $this->t('Relatie A-Z'),
      ],
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Toepassen'),
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $projectId = (int) $form_state->get('project_id');
    $form_state->setRedirect('brebo_project_cockpit.invoices', ['node' => $projectId], [
      'query' => [
        'direction' => (string) $form_state->getValue('direction'),
        'state' => (string) $form_state->getValue('state'),
        'sort' => (string) $form_state->getValue('sort'),
      ],
    ]);
  }

}
