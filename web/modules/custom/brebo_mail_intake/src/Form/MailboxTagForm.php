<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Edits lightweight user-managed tags for one mailbox communication. */
final class MailboxTagForm extends FormBase {

  public function __construct(private readonly Connection $database) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function getFormId(): string {
    return 'brebo_mailbox_tag_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $mailbox_id = 0, int $communication_id = 0, string $mail_state = 'inbox'): array {
    $form['#attributes']['class'][] = 'brebo-mail-tags-form';
    $form['mailbox_id'] = ['#type' => 'hidden', '#value' => $mailbox_id];
    $form['communication_id'] = ['#type' => 'hidden', '#value' => $communication_id];
    $form['mail_state'] = ['#type' => 'hidden', '#value' => $mail_state];

    $tags = [];
    if ($communication_id > 0 && $this->database->schema()->tableExists('brebo_mail_tag')) {
      $tags = $this->database->select('brebo_mail_tag', 't')
        ->fields('t', ['tag'])
        ->condition('communication_id', $communication_id)
        ->orderBy('tag')
        ->execute()
        ->fetchCol();
    }

    $form['tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tags'),
      '#title_display' => 'invisible',
      '#default_value' => implode(', ', $tags),
      '#placeholder' => $this->t('Tags toevoegen, gescheiden door komma’s'),
      '#maxlength' => 1000,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Opslaan'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->database->schema()->tableExists('brebo_mail_tag')) {
      $this->messenger()->addError($this->t('De tag-opslag is nog niet geïnstalleerd. Voer eerst de database-updates uit.'));
      return;
    }

    $communicationId = (int) $form_state->getValue('communication_id');
    $mailboxId = (int) $form_state->getValue('mailbox_id');
    $mailState = (string) $form_state->getValue('mail_state');
    $raw = (string) $form_state->getValue('tags');

    $tags = array_values(array_unique(array_filter(array_map(
      static function (string $tag): string {
        $tag = trim(strip_tags($tag));
        return mb_substr($tag, 0, 64);
      },
      preg_split('/[,;\n]+/u', $raw) ?: [],
    ))));
    $tags = array_slice($tags, 0, 20);

    $transaction = $this->database->startTransaction();
    try {
      $this->database->delete('brebo_mail_tag')->condition('communication_id', $communicationId)->execute();
      $now = time();
      foreach ($tags as $tag) {
        $this->database->insert('brebo_mail_tag')->fields([
          'communication_id' => $communicationId,
          'tag' => $tag,
          'created' => $now,
          'uid' => (int) $this->currentUser()->id(),
        ])->execute();
      }
    }
    catch (\Throwable $e) {
      unset($transaction);
      throw $e;
    }

    $this->messenger()->addStatus($this->t('Tags opgeslagen.'));
    $form_state->setRedirectUrl(Url::fromRoute('brebo_mail_intake.mailbox_message', [
      'mailbox_id' => $mailboxId,
      'mail_state' => $mailState,
      'communication_id' => $communicationId,
    ]));
  }

}
