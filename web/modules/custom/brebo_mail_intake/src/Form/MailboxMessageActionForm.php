<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Form;

use Drupal\brebo_mail_intake\Service\MailboxAccessPolicy;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides safe state-changing actions for one projected mailbox message.
 */
final class MailboxMessageActionForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly MailboxAccessPolicy $accessPolicy,
    private readonly AccountProxyInterface $currentAccount,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('brebo_mail_intake.mailbox_access_policy'),
      $container->get('current_user'),
    );
  }

  public function getFormId(): string {
    return 'brebo_mailbox_message_action_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $mailbox_id = 0, int $communication_id = 0, string $mail_state = 'inbox'): array {
    if ($mailbox_id <= 0 || $communication_id <= 0 || !$this->accessPolicy->allowed($this->currentAccount, $mailbox_id, 'view')) {
      return [];
    }

    $row = $this->database->select('brebo_mailbox_message', 'bm')
      ->fields('bm', ['mail_state', 'is_read', 'is_starred', 'needs_action'])
      ->condition('mailbox_id', $mailbox_id)
      ->condition('communication_id', $communication_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      return [];
    }

    $form['#attributes']['class'][] = 'brebo-mail-state-actions';
    $form['mailbox_id'] = ['#type' => 'hidden', '#value' => $mailbox_id];
    $form['communication_id'] = ['#type' => 'hidden', '#value' => $communication_id];
    $form['return_state'] = ['#type' => 'hidden', '#value' => $mail_state];

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

    if (!$this->accessPolicy->allowed($this->currentAccount, $mailboxId, 'view')) {
      $this->messenger()->addError($this->t('U heeft geen toegang tot deze mailbox.'));
      return;
    }

    $row = $this->database->select('brebo_mailbox_message', 'bm')
      ->fields('bm', ['mail_state', 'is_read', 'is_starred', 'needs_action'])
      ->condition('mailbox_id', $mailboxId)
      ->condition('communication_id', $communicationId)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      $this->messenger()->addError($this->t('Het bericht is niet meer aan deze mailbox gekoppeld.'));
      return;
    }

    $trigger = (string) ($form_state->getTriggeringElement()['#name'] ?? '');
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

    $this->database->update('brebo_mailbox_message')
      ->fields($fields)
      ->condition('mailbox_id', $mailboxId)
      ->condition('communication_id', $communicationId)
      ->execute();

    $form_state->setRedirect('brebo_mail_intake.mailbox_message', [
      'mailbox_id' => $mailboxId,
      'mail_state' => $redirectState,
      'communication_id' => $communicationId,
    ]);
  }

}
