<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Form;

use Drupal\brebo_mail_intake\Service\MailboxAccessPolicy;
use Drupal\brebo_mail_intake\Service\MailboxRepository;
use Drupal\brebo_mail_intake\Service\OutboundMailService;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Explicitly approves and sends one BREBO mail draft. */
final class MailSendForm extends ConfirmFormBase {

  private int $mailboxId = 0;
  private int $communicationId = 0;
  private ?NodeInterface $communication = NULL;

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

  public function getFormId(): string {
    return 'brebo_mail_send_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $mailbox_id = 0, int $communication_id = 0): array {
    $this->mailboxId = $mailbox_id;
    $this->communicationId = $communication_id;

    if (!$this->mailboxes->load($mailbox_id)) {
      throw new NotFoundHttpException('Mailbox niet gevonden.');
    }
    if (!$this->accessPolicy->allowed($this->mailCurrentUser, $mailbox_id, 'view')) {
      throw new AccessDeniedHttpException();
    }

    $membership = $this->database->select('brebo_mailbox_message', 'bm')
      ->fields('bm', ['mail_state'])
      ->condition('mailbox_id', $mailbox_id)
      ->condition('communication_id', $communication_id)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    $node = $this->entityTypeManager->getStorage('node')->load($communication_id);
    if ($membership !== 'draft' || !$node instanceof NodeInterface || $node->bundle() !== 'brebo_communication' || !$node->access('view', $this->mailCurrentUser)) {
      throw new NotFoundHttpException('Mailconcept niet gevonden of niet meer verzendbaar.');
    }
    if (trim((string) $node->get('field_brebo_comm_direction')->value) !== 'Uitgaand' || trim((string) $node->get('field_brebo_comm_status')->value) === 'Verzonden') {
      throw new NotFoundHttpException('Dit bericht is geen verzendbaar concept.');
    }
    $this->communication = $node;

    $form['summary'] = [
      '#markup' => '<div class="brebo-mail-send-summary"><strong>Aan:</strong> ' .
        htmlspecialchars((string) $node->get('field_brebo_mail_to')->value, ENT_QUOTES, 'UTF-8') .
        '<br><strong>Onderwerp:</strong> ' .
        htmlspecialchars((string) $node->get('field_brebo_comm_subject')->value, ENT_QUOTES, 'UTF-8') .
        '</div>',
      '#weight' => -10,
    ];
    return parent::buildForm($form, $form_state);
  }

  public function getQuestion(): string {
    return (string) $this->t('Dit mailconcept goedkeuren en verzenden?');
  }

  public function getDescription(): string {
    return (string) $this->t('Na bevestiging wordt het bericht daadwerkelijk verzonden en verplaatst naar Verzonden.');
  }

  public function getConfirmText(): string {
    return (string) $this->t('Goedkeuren en verzenden');
  }

  public function getCancelText(): string {
    return (string) $this->t('Terug naar concept');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('brebo_mail_intake.mailbox_message', [
      'mailbox_id' => $this->mailboxId,
      'mail_state' => 'draft',
      'communication_id' => $this->communicationId,
    ]);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->communication instanceof NodeInterface) {
      throw new \RuntimeException('Mailconcept kon niet worden geladen.');
    }

    $this->communication->set('field_brebo_formal_status', 'Verzenden goedgekeurd');
    $this->communication->setNewRevision(TRUE);
    $this->communication->setRevisionLogMessage('Uitgaande e-mail expliciet vrijgegeven voor verzending door gebruiker ' . (int) $this->mailCurrentUser->id() . '.');
    $this->communication->save();

    try {
      $this->outbound->send($this->communication);
    }
    catch (\Throwable $exception) {
      $this->getLogger('brebo_mail_intake')->error('Verzenden van communicatie @id is mislukt: @message', [
        '@id' => $this->communicationId,
        '@message' => $exception->getMessage(),
      ]);
      $this->messenger()->addError($this->t('Verzenden is niet gelukt: @message', ['@message' => $exception->getMessage()]));
      $form_state->setRedirect('brebo_mail_intake.mailbox_message', [
        'mailbox_id' => $this->mailboxId,
        'mail_state' => 'draft',
        'communication_id' => $this->communicationId,
      ]);
      return;
    }

    $this->database->update('brebo_mailbox_message')
      ->fields(['mail_state' => 'sent', 'is_read' => 1, 'changed' => time()])
      ->condition('mailbox_id', $this->mailboxId)
      ->condition('communication_id', $this->communicationId)
      ->execute();

    $this->messenger()->addStatus($this->t('E-mail is verzonden en staat in Verzonden.'));
    $form_state->setRedirect('brebo_mail_intake.mailbox_message', [
      'mailbox_id' => $this->mailboxId,
      'mail_state' => 'sent',
      'communication_id' => $this->communicationId,
    ]);
  }

}
