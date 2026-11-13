<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Form;

use Drupal\brebo_mail_intake\Service\MailboxAccessPolicy;
use Drupal\brebo_mail_intake\Service\MailboxRepository;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\brebo_mail_intake\Contract\MailboxStorageRepositoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides safe state-changing actions for one projected mailbox message.
 */
final class MailboxMessageActionForm extends FormBase {

  public function __construct(
    private readonly MailboxStorageRepositoryInterface $storage,
    private readonly MailboxAccessPolicy $accessPolicy,
    private readonly AccountProxyInterface $currentAccount,
    private readonly MailboxRepository $mailboxes,
    private readonly QueueFactory $queueFactory,
    private readonly LockBackendInterface $lock,
    private readonly StateInterface $state,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_mail_intake.mailbox_storage_repository'),
      $container->get('brebo_mail_intake.mailbox_access_policy'),
      $container->get('current_user'),
      $container->get('brebo_mail_intake.mailbox_repository'),
      $container->get('queue'),
      $container->get('lock'),
      $container->get('state'),
    );
  }

  public function getFormId(): string {
    return 'brebo_mailbox_message_action_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $mailbox_id = 0, int $communication_id = 0, string $mail_state = 'inbox'): array {
    if ($mailbox_id <= 0 || $communication_id <= 0 || !$this->accessPolicy->allowed((int) $this->currentAccount->id(), $this->currentAccount->getRoles(), $this->currentAccount->hasPermission('administer site configuration'), $mailbox_id, 'view')) {
      return [];
    }

    $row = $this->storage->messageState($mailbox_id, $communication_id);

    if ($row === NULL) {
      return [];
    }

    $form['#attributes']['class'][] = 'brebo-mail-state-actions';
    $form['mailbox_id'] = ['#type' => 'hidden', '#value' => $mailbox_id];
    $form['communication_id'] = ['#type' => 'hidden', '#value' => $communication_id];
    $form['return_state'] = ['#type' => 'hidden', '#value' => $mail_state];

    $mailbox = $this->mailboxes->load($mailbox_id);
    if (($mailbox['privacy_type'] ?? '') === 'personal'
      && (int) ($mailbox['owner_uid'] ?? 0) === (int) $this->currentAccount->id()) {
      $form['push_to_office'] = [
        '#type' => 'submit',
        '#name' => 'push_to_office',
        '#value' => $this->t('Push naar Office'),
        '#attributes' => ['class' => ['brebo-mail-action', 'brebo-mail-action--primary']],
      ];
    }

    $form['read'] = [
      '#type' => 'submit',
      '#name' => 'toggle_read',
      '#value' => $row['is_read'] ? $this->t('◉ Ongelezen') : $this->t('○ Gelezen'),
      '#attributes' => ['class' => ['brebo-mail-action']],
    ];
    $form['star'] = [
      '#type' => 'submit',
      '#name' => 'toggle_star',
      '#value' => $row['is_starred'] ? $this->t('☆ Ster verwijderen') : $this->t('★ Ster'),
      '#attributes' => ['class' => ['brebo-mail-action']],
    ];
    $form['action'] = [
      '#type' => 'submit',
      '#name' => 'toggle_action',
      '#value' => $row['needs_action'] ? $this->t('✓ Actie gereed') : $this->t('⚑ Actie nodig'),
      '#attributes' => ['class' => ['brebo-mail-action']],
    ];

    if ($row['mail_state'] !== 'archive') {
      $form['archive'] = [
        '#type' => 'submit',
        '#name' => 'archive',
        '#value' => $this->t('▣ Archiveren'),
        '#attributes' => ['class' => ['brebo-mail-action']],
      ];
    }
    if ($row['mail_state'] !== 'trash') {
      $form['trash'] = [
        '#type' => 'submit',
        '#name' => 'trash',
        '#value' => $this->t('⌫ Prullenbak'),
        '#attributes' => ['class' => ['brebo-mail-action']],
      ];
    }
    if (in_array($row['mail_state'], ['archive', 'trash', 'spam'], TRUE)) {
      $form['restore'] = [
        '#type' => 'submit',
        '#name' => 'restore',
        '#value' => $this->t('↩ Naar Postvak IN'),
        '#attributes' => ['class' => ['brebo-mail-action']],
      ];
    }

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $mailboxId = (int) $form_state->getValue('mailbox_id');
    $communicationId = (int) $form_state->getValue('communication_id');
    $returnState = (string) $form_state->getValue('return_state');

    if (!$this->accessPolicy->allowed((int) $this->currentAccount->id(), $this->currentAccount->getRoles(), $this->currentAccount->hasPermission('administer site configuration'), $mailboxId, 'view')) {
      $this->messenger()->addError($this->t('U heeft geen toegang tot deze mailbox.'));
      return;
    }

    $row = $this->storage->messageState($mailboxId, $communicationId);

    if ($row === NULL) {
      $this->messenger()->addError($this->t('Het bericht is niet meer aan deze mailbox gekoppeld.'));
      return;
    }

    $trigger = (string) ($form_state->getTriggeringElement()['#name'] ?? '');
    if ($trigger === 'push_to_office') {
      $mailbox = $this->mailboxes->load($mailboxId);
      if (($mailbox['privacy_type'] ?? '') !== 'personal'
        || empty($mailbox['active'])
        || (int) ($mailbox['owner_uid'] ?? 0) !== (int) $this->currentAccount->id()) {
        $this->messenger()->addError($this->t('Geen toestemming om dit bericht te pushen.'));
        return;
      }
      // One pending push per mailbox/message. Lock prevents concurrent clicks
      // from creating duplicate work items. This is an admission marker only:
      // consumers must separately deduplicate durable dossier proposals.
      $key = 'brebo_personal_mail_push.pending.' . $mailboxId . '.' . $communicationId;
      if (!$this->lock->acquire($key, 15.0)) {
        $this->messenger()->addWarning($this->t('Dit bericht wordt al aangemeld.'));
        return;
      }
      try {
        if ($this->state->get($key, FALSE)) {
          $this->messenger()->addStatus($this->t('Dit bericht staat al klaar voor Office-verwerking.'));
        }
        else {
          $queued = $this->queueFactory->get('brebo_personal_mail_push')->createItem([
            'mailbox_id' => $mailboxId,
            'communication_id' => $communicationId,
            'actor_uid' => (int) $this->currentAccount->id(),
            'requested_at' => time(),
          ]);
          if ($queued === FALSE) {
            throw new \RuntimeException('Queue item kon niet worden opgeslagen.');
          }
          $this->state->set($key, ['actor_uid' => (int) $this->currentAccount->id(), 'queued_at' => time()]);
          $this->messenger()->addStatus($this->t('Bericht aangemeld voor Office-verwerking; dossierkoppeling is nog niet uitgevoerd.'));
        }
      }
      catch (\Throwable $e) {
        $this->getLogger('brebo_mail_intake')->error('Persoonlijke mailpush kon niet worden aangemeld: @error', ['@error' => $e->getMessage()]);
        $this->messenger()->addError($this->t('Office-verwerking kon niet worden aangemeld. Probeer later opnieuw.'));
        return;
      }
      finally {
        $this->lock->release($key);
      }
      $form_state->setRedirect('brebo_mail_intake.mailbox_message', [
        'mailbox_id' => $mailboxId, 'mail_state' => $returnState, 'communication_id' => $communicationId,
      ]);
      return;
    }

    $fields = ['changed' => time()];
    $redirectState = $returnState;

    switch ($trigger) {
      case 'toggle_read':
        $fields['is_read'] = empty($row['is_read']) ? 1 : 0;
        break;

      case 'toggle_star':
        $fields['is_starred'] = empty($row['is_starred']) ? 1 : 0;
        break;

      case 'toggle_action':
        $fields['needs_action'] = empty($row['needs_action']) ? 1 : 0;
        break;

      case 'archive':
        $fields['mail_state'] = 'archive';
        $redirectState = 'archive';
        break;

      case 'trash':
        $fields['mail_state'] = 'trash';
        $redirectState = 'trash';
        break;

      case 'restore':
        $fields['mail_state'] = 'inbox';
        $redirectState = 'inbox';
        break;

      default:
        return;
    }

    $this->storage->updateMessage($mailboxId, $communicationId, $fields);

    $form_state->setRedirect('brebo_mail_intake.mailbox_message', [
      'mailbox_id' => $mailboxId,
      'mail_state' => $redirectState,
      'communication_id' => $communicationId,
    ]);
  }

}
