<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Form;

use Drupal\brebo_finance\Service\BillingControlManager;
use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Approves a reached billing instalment as billable under Finance controls. */
final class ProjectInstalmentBillableApprovalForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly BillingControlManager $billingManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('brebo_finance.billing_control_manager'),
    );
  }

  public function getFormId(): string {
    return 'brebo_project_cockpit_instalment_billable_approval_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, ?int $instalment = NULL): array {
    if ($node === NULL || $node->bundle() !== 'brebo_project' || $instalment === NULL) {
      throw new NotFoundHttpException('Project and billing instalment required.');
    }

    $row = $this->database->select('brebo_finance_billing_instalment', 'i')
      ->fields('i')
      ->condition('id', $instalment)
      ->condition('project_nid', (int) $node->id())
      ->execute()
      ->fetchAssoc();

    if ($row === FALSE) {
      throw new NotFoundHttpException('Billing instalment not found for this project.');
    }

    $form['summary'] = [
      '#markup' => '<p><strong>' . $this->t('Termijn:') . '</strong> ' . Html::escape((string) ($row['instalment_number'] ?? '—'))
        . '<br><strong>' . $this->t('Omschrijving:') . '</strong> ' . Html::escape((string) ($row['description'] ?? '—'))
        . '<br><strong>' . $this->t('Bedrag excl. btw:') . '</strong> € ' . number_format((float) ($row['amount_ex_vat'] ?? 0), 2, ',', '.')
        . '<br><strong>' . $this->t('Status:') . '</strong> ' . Html::escape((string) ($row['status'] ?? '—')) . '</p>',
    ];

    if (($row['status'] ?? '') !== 'planned') {
      $form['warning'] = ['#markup' => '<p><strong>' . $this->t('Alleen een geplande termijn kan als factureerbaar worden goedgekeurd.') . '</strong></p>'];
      $form['actions']['cancel'] = [
        '#type' => 'link',
        '#title' => $this->t('Terug naar Facturen'),
        '#url' => Url::fromRoute('brebo_project_cockpit.invoices', ['node' => (int) $node->id()]),
        '#attributes' => ['class' => ['button']],
      ];
      return $form;
    }

    $form['trigger_evidence'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Geverifieerd triggerbewijs'),
      '#required' => TRUE,
      '#description' => $this->t('Leg vast waarom deze termijn nu factureerbaar is, bijvoorbeeld bereikte mijlpaal, geverifieerde voortgang, contractdatum of akkoord op meerwerk.'),
    ];

    $form['controls'] = [
      '#markup' => '<p>' . $this->t('Bij goedkeuring worden de bestaande Finance-controls toegepast: billing phase gate, triggerbewijs en vier-ogencontrole. De maker van de termijn kan zijn eigen termijn niet factureerbaar verklaren.') . '</p>',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Termijn factureerbaar goedkeuren'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Annuleren'),
      '#url' => Url::fromRoute('brebo_project_cockpit.invoices', ['node' => (int) $node->id()]),
      '#attributes' => ['class' => ['button']],
    ];

    $form_state->set('project_id', (int) $node->id());
    $form_state->set('instalment_id', (int) $row['id']);
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $projectId = (int) $form_state->get('project_id');
    $instalmentId = (int) $form_state->get('instalment_id');

    try {
      $this->billingManager->approveBillable(
        $instalmentId,
        trim((string) $form_state->getValue('trigger_evidence')),
        (int) $this->currentUser()->id(),
      );
    }
    catch (\InvalidArgumentException | \RuntimeException $exception) {
      $this->messenger()->addError($exception->getMessage());
      $form_state->setRebuild();
      return;
    }

    $this->messenger()->addStatus($this->t('De termijn is gecontroleerd vrijgegeven als factureerbaar.'));
    $form_state->setRedirect('brebo_project_cockpit.invoices', ['node' => $projectId]);
  }

}
