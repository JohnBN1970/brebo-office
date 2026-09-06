<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Form;

use Drupal\brebo_data_intake\Service\IntakeDecisionManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Human decision form for one source-neutral intake record. */
final class IntakeReviewDecisionForm extends FormBase {

  public function __construct(
    private readonly IntakeDecisionManager $decisions,
    private readonly AccountProxyInterface $currentUserAccount,
    private readonly MessengerInterface $messengerService,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_data_intake.decision_manager'),
      $container->get('current_user'),
      $container->get('messenger'),
    );
  }

  public function getFormId(): string {
    return 'brebo_data_intake_review_decision_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $record = NULL): array {
    if ($record === NULL) {
      throw new NotFoundHttpException();
    }
    $snapshot = $this->decisions->snapshot($record);
    if ($snapshot === NULL) {
      throw new NotFoundHttpException();
    }
    if ($snapshot['status'] !== 'review_required') {
      $form['closed'] = [
        '#markup' => '<p>' . $this->t('Dit intake-item is al beoordeeld en staat niet meer in de wachtrij.') . '</p>',
      ];
      return $form;
    }

    $stored = is_array($snapshot['payload']) ? $snapshot['payload'] : [];
    $envelope = is_array($stored['envelope'] ?? NULL) ? $stored['envelope'] : [];
    $canonical = is_array($envelope['canonical'] ?? NULL) ? $envelope['canonical'] : [];

    $form['record_id'] = ['#type' => 'hidden', '#value' => $record];
    $form['revision'] = ['#type' => 'hidden', '#value' => (string) $snapshot['revision']];
    $form['classification'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Classificatie'),
      '#default_value' => (string) ($envelope['classification'] ?? ''),
      '#required' => TRUE,
      '#description' => $this->t('Bijvoorbeeld purchase_invoice, project_communication, document of request.'),
    ];
    $form['project_nid'] = [
      '#type' => 'number',
      '#title' => $this->t('Project-ID'),
      '#default_value' => isset($canonical['project_nid']) ? (int) $canonical['project_nid'] : NULL,
      '#min' => 1,
      '#description' => $this->t('Leeg laten als dit item niet aan een project hoort.'),
    ];
    $form['building_nid'] = [
      '#type' => 'number',
      '#title' => $this->t('Gebouw-ID'),
      '#default_value' => isset($canonical['building_nid']) ? (int) $canonical['building_nid'] : NULL,
      '#min' => 1,
    ];
    $form['relationship_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Relatie-ID'),
      '#default_value' => (string) ($canonical['relationship_id'] ?? ''),
    ];
    $form['contact_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact-ID'),
      '#default_value' => (string) ($canonical['contact_id'] ?? ''),
    ];
    $form['supplier_ref'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Leveranciersreferentie'),
      '#default_value' => (string) ($canonical['supplier_ref'] ?? ''),
    ];
    $form['note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Toelichting'),
      '#rows' => 3,
      '#description' => $this->t('Verplicht bij afwijzen; bij andere acties optioneel en opgenomen in de audittrail.'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['correct'] = [
      '#type' => 'submit',
      '#value' => $this->t('Correctie opslaan'),
      '#submit' => ['::submitCorrection'],
    ];
    $form['actions']['accept'] = [
      '#type' => 'submit',
      '#value' => $this->t('Accepteren en doorzetten'),
      '#button_type' => 'primary',
      '#submit' => ['::submitAccept'],
    ];
    $form['actions']['reject'] = [
      '#type' => 'submit',
      '#value' => $this->t('Afwijzen'),
      '#submit' => ['::submitReject'],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  public function submitCorrection(array &$form, FormStateInterface $form_state): void {
    try {
      $result = $this->decisions->correct(
        (int) $form_state->getValue('record_id'),
        (string) $form_state->getValue('revision'),
        (string) $form_state->getValue('classification'),
        $this->canonicalFromState($form_state),
        (int) $this->currentUserAccount->id(),
        (string) $form_state->getValue('note'),
      );
      $this->messengerService->addStatus($result['state'] === 'unchanged'
        ? $this->t('Er waren geen wijzigingen om op te slaan.')
        : $this->t('De correctie is opgeslagen en blijft wachten op beoordeling.'));
      $form_state->setRedirect('brebo_data_intake.review');
    }
    catch (\RuntimeException $e) {
      $this->messengerService->addError($e->getMessage());
      $form_state->setRebuild();
    }
  }

  public function submitAccept(array &$form, FormStateInterface $form_state): void {
    try {
      $recordId = (int) $form_state->getValue('record_id');
      $revision = (string) $form_state->getValue('revision');
      $correction = $this->decisions->correct(
        $recordId,
        $revision,
        (string) $form_state->getValue('classification'),
        $this->canonicalFromState($form_state),
        (int) $this->currentUserAccount->id(),
        (string) $form_state->getValue('note'),
      );
      $revision = (string) ($correction['revision'] ?? $revision);
      $this->decisions->accept(
        $recordId,
        $revision,
        (int) $this->currentUserAccount->id(),
        (string) $form_state->getValue('note'),
      );
      $this->messengerService->addStatus($this->t('Het intake-item is geaccepteerd en via het destination-contract doorgezet.'));
      $form_state->setRedirect('brebo_data_intake.review');
    }
    catch (\RuntimeException $e) {
      $this->messengerService->addError($e->getMessage());
      $form_state->setRebuild();
    }
  }

  public function submitReject(array &$form, FormStateInterface $form_state): void {
    try {
      $this->decisions->reject(
        (int) $form_state->getValue('record_id'),
        (string) $form_state->getValue('revision'),
        (int) $this->currentUserAccount->id(),
        (string) $form_state->getValue('note'),
      );
      $this->messengerService->addStatus($this->t('Het intake-item is afgewezen en auditeerbaar afgesloten.'));
      $form_state->setRedirect('brebo_data_intake.review');
    }
    catch (\RuntimeException $e) {
      $this->messengerService->addError($e->getMessage());
      $form_state->setRebuild();
    }
  }

  /** @return array<string,mixed> */
  private function canonicalFromState(FormStateInterface $form_state): array {
    return [
      'relationship_id' => $form_state->getValue('relationship_id'),
      'project_nid' => $form_state->getValue('project_nid'),
      'building_nid' => $form_state->getValue('building_nid'),
      'supplier_ref' => $form_state->getValue('supplier_ref'),
      'contact_id' => $form_state->getValue('contact_id'),
    ];
  }

}
