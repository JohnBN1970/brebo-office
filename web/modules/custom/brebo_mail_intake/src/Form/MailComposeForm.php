<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Form;

use Drupal\brebo_mail_intake\Service\MailboxAccessPolicy;
use Drupal\brebo_mail_intake\Service\MailboxRepository;
use Drupal\brebo_mail_intake\Service\OutboundMailService;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Creates auditable BREBO outbound mail drafts from the mailbox workspace. */
final class MailComposeForm extends FormBase {
  public function __construct(
    private readonly OutboundMailService $outbound,
    private readonly MailboxRepository $mailboxes,
    private readonly MailboxAccessPolicy $accessPolicy,
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $mailCurrentUser,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_mail_intake.outbound'),
      $container->get('brebo_mail_intake.mailbox_repository'),
      $container->get('brebo_mail_intake.mailbox_access_policy'),
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  public function getFormId(): string { return 'brebo_mail_compose_form'; }

  public function buildForm(array $form, FormStateInterface $form_state, int $mailbox_id = 0, string $mode = 'new', int $communication_id = 0): array {
    $mailbox = $this->mailboxes->load($mailbox_id);
    if (!$mailbox) { throw new NotFoundHttpException('Mailbox niet gevonden.'); }
    if (!$this->accessPolicy->allowed($this->mailCurrentUser, $mailbox_id, 'view')) { throw new AccessDeniedHttpException(); }

    $mode = in_array($mode, ['new', 'reply', 'forward'], TRUE) ? $mode : 'new';
    $source = $communication_id > 0 ? $this->loadSource($communication_id) : NULL;
    if ($mode !== 'new' && !$source) { throw new NotFoundHttpException('Bronbericht niet gevonden of niet toegankelijk.'); }

    $to = $subject = $body = '';
    if ($source) {
      $sourceSubject = trim((string) ($source->get('field_brebo_comm_subject')->value ?? $source->label()));
      $sourceBody = trim((string) ($source->get('field_brebo_transcript')->value ?? ''));
      $sourceFrom = trim((string) ($source->get('field_brebo_mail_from')->value ?? ''));
      $sourceDate = trim((string) ($source->get('field_brebo_comm_datetime')->value ?? ''));
      if ($mode === 'reply') {
        $to = $this->extractAddress($sourceFrom);
        $subject = preg_match('/^re:/i', $sourceSubject) ? $sourceSubject : 'Re: ' . $sourceSubject;
        $body = "\n\n--- Oorspronkelijk bericht ---\nVan: {$sourceFrom}\nDatum: {$sourceDate}\n\n{$sourceBody}";
      }
      elseif ($mode === 'forward') {
        $subject = preg_match('/^(fw|fwd):/i', $sourceSubject) ? $sourceSubject : 'Fwd: ' . $sourceSubject;
        $body = "\n\n--- Doorgestuurd bericht ---\nVan: {$sourceFrom}\nDatum: {$sourceDate}\nOnderwerp: {$sourceSubject}\n\n{$sourceBody}";
      }
    }

    $form['intro'] = ['#markup' => '<div class="brebo-mail-compose-intro"><strong>Conceptbericht</strong><br>Opslaan maakt een BREBO Communication-concept. Er wordt nog niets verzonden.</div>'];
    $form['mailbox_id'] = ['#type' => 'hidden', '#value' => $mailbox_id];
    $form['mode'] = ['#type' => 'hidden', '#value' => $mode];
    $form['source_id'] = ['#type' => 'hidden', '#value' => $communication_id];
    $form['to'] = ['#type' => 'email', '#title' => $this->t('Aan'), '#required' => TRUE, '#default_value' => $to, '#maxlength' => 320];
    $form['subject'] = ['#type' => 'textfield', '#title' => $this->t('Onderwerp'), '#required' => TRUE, '#default_value' => $subject, '#maxlength' => 255];
    $form['body'] = ['#type' => 'textarea', '#title' => $this->t('Bericht'), '#required' => TRUE, '#default_value' => $body, '#rows' => 16];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['save'] = ['#type' => 'submit', '#value' => $this->t('Concept opslaan'), '#button_type' => 'primary'];
    $form['actions']['cancel'] = ['#type' => 'link', '#title' => $this->t('Annuleren'), '#url' => Url::fromRoute('brebo_mail_intake.mailbox', ['mailbox_id' => $mailbox_id, 'mail_state' => 'inbox']), '#attributes' => ['class' => ['button']]];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $mailboxId = (int) $form_state->getValue('mailbox_id');
    $sourceId = (int) $form_state->getValue('source_id');
    $mode = (string) $form_state->getValue('mode');
    $draft = $this->outbound->createDraft([
      'to' => trim((string) $form_state->getValue('to')),
      'subject' => trim((string) $form_state->getValue('subject')),
      'body' => trim((string) $form_state->getValue('body')),
    ]);
    $this->database->merge('brebo_mailbox_message')
      ->keys(['mailbox_id' => $mailboxId, 'communication_id' => (int) $draft->id()])
      ->fields(['mail_state' => 'draft', 'is_read' => 1, 'is_starred' => 0, 'needs_action' => 0, 'changed' => time()])
      ->execute();
    if ($sourceId > 0 && in_array($mode, ['reply', 'forward'], TRUE)) {
      $draft->setNewRevision(TRUE);
      $draft->setRevisionLogMessage(sprintf('Concept %s aangemaakt vanuit communicatie %d; bronbericht blijft ongewijzigd.', $mode, $sourceId));
      $draft->save();
    }
    $this->messenger()->addStatus($this->t('Concept opgeslagen in BREBO Office. Er is nog niets verzonden.'));
    $form_state->setRedirect('brebo_mail_intake.mailbox_message', ['mailbox_id' => $mailboxId, 'mail_state' => 'draft', 'communication_id' => (int) $draft->id()]);
  }

  private function loadSource(int $communicationId): ?NodeInterface {
    $node = $this->entityTypeManager->getStorage('node')->load($communicationId);
    return $node instanceof NodeInterface && $node->bundle() === 'brebo_communication' && $node->access('view', $this->mailCurrentUser) ? $node : NULL;
  }

  private function extractAddress(string $from): string {
    if (preg_match('/<([^>]+)>/', $from, $matches)) { return trim($matches[1]); }
    return filter_var(trim($from), FILTER_VALIDATE_EMAIL) ? trim($from) : '';
  }
}
