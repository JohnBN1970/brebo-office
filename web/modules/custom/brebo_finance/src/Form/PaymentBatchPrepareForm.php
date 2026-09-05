<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Form;

use Drupal\brebo_finance\Service\PaymentBatchManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Selects approved payment releases and prepares one controlled payment batch. */
final class PaymentBatchPrepareForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly PaymentBatchManager $batches,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('brebo_finance.payment_batch_manager'),
    );
  }

  public function getFormId(): string {
    return 'brebo_finance_payment_batch_prepare';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $options = [];
    if ($this->database->schema()->tableExists('brebo_finance_payment_release')) {
      $query = $this->database->select('brebo_finance_payment_release', 'r');
      $query->leftJoin('brebo_finance_purchase_invoice', 'i', 'i.id = r.invoice_id');
      $query->fields('r');
      $query->addField('i', 'invoice_number');
      $query->addField('i', 'supplier_name');
      $query->condition('r.status', 'approved');
      $query->orderBy('r.created', 'DESC');
      $query->range(0, 100);

      foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $release) {
        $id = (int) $release['id'];
        $options[$id] = [
          'supplier' => (string) ($release['supplier_name'] ?? ''),
          'invoice' => (string) ($release['invoice_number'] ?? ''),
          'release' => (string) ($release['release_number'] ?? ''),
          'amount' => '€ ' . number_format((float) $release['total_amount'], 2, ',', '.'),
        ];
      }
    }

    $form['releases'] = [
      '#type' => 'tableselect',
      '#header' => [
        'supplier' => $this->t('Leverancier'),
        'invoice' => $this->t('Factuur'),
        'release' => $this->t('Vrijgave'),
        'amount' => $this->t('Bedrag'),
      ],
      '#options' => $options,
      '#empty' => $this->t('Geen goedgekeurde betaalvrijgaven beschikbaar.'),
      '#js_select' => TRUE,
    ];

    $form['execution_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Uitvoerdatum'),
      '#default_value' => date('Y-m-d'),
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Geselecteerde regels in betaalbatch'),
      '#button_type' => 'primary',
      '#disabled' => $options === [] || !$this->currentUser()->hasPermission('manage brebo finance'),
    ];

    if (!$this->currentUser()->hasPermission('manage brebo finance')) {
      $form['permission_notice'] = [
        '#markup' => '<p><em>' . $this->t('Je hebt geen recht om betaalbatches voor te bereiden.') . '</em></p>',
      ];
    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $selected = array_values(array_filter(array_map('intval', (array) $form_state->getValue('releases'))));
    if ($selected === []) {
      $form_state->setErrorByName('releases', $this->t('Selecteer minimaal één regel voor de betaalbatch.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->currentUser()->hasPermission('manage brebo finance')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $releaseIds = array_values(array_filter(array_map('intval', (array) $form_state->getValue('releases'))));
    try {
      $batchId = $this->batches->prepare(
        $releaseIds,
        (string) $form_state->getValue('execution_date'),
        (int) $this->currentUser()->id(),
      );
      $this->messenger()->addStatus($this->t('Betaalbatch @id is voorbereid met @count geselecteerde regel(s).', [
        '@id' => $batchId,
        '@count' => count($releaseIds),
      ]));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
    }

    $form_state->setRedirect('brebo_finance.payment_center');
  }

}
