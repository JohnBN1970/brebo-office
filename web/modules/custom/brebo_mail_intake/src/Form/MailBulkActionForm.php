<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Form;

use Drupal\brebo_mail_intake\Service\MailboxAccessPolicy;
use Drupal\brebo_mail_intake\Service\MailboxRepository;
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

/** Applies one controlled mailbox or Office action to selected messages. */
final class MailBulkActionForm extends FormBase {

  public function __construct(
    private readonly MailboxRepository $mailboxes,
    private readonly MailboxAccessPolicy $accessPolicy,
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $mailCurrentUser,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_mail_intake.mailbox_repository'),
      $container->get('brebo_mail_intake.mailbox_access_policy'),
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  public function getFormId(): string {
    return 'brebo_mail_bulk_action_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $mailbox_id = 0, string $mail_state = 'inbox'): array {
    if (!$this->mailboxes->load($mailbox_id)) {
      throw new NotFoundHttpException('Mailbox niet gevonden.');
    }
    if (!$this->accessPolicy->allowed($this->mailCurrentUser, $mailbox_id, 'view')) {
      throw new AccessDeniedHttpException();
    }
    $allowedStates = ['inbox', 'sent', 'draft', 'spam', 'archive', 'trash'];
    if (!in_array($mail_state, $allowedStates, TRUE)) {
      throw new NotFoundHttpException('Onbekende mailmap.');
    }

    $query = $this->database->select('brebo_mailbox_message', 'bm');
    $query->join('node_field_data', 'n', 'n.nid = bm.communication_id AND n.default_langcode = 1');
    $query->leftJoin('node__field_brebo_mail_from', 'mf', 'mf.entity_id = n.nid AND mf.deleted = 0');
    $query->leftJoin('node__field_brebo_comm_subject', 'ms', 'ms.entity_id = n.nid AND ms.deleted = 0');
    $query->fields('bm', ['communication_id']);
    $query->addField('mf', 'field_brebo_mail_from_value', 'mail_from');
    $query->addField('ms', 'field_brebo_comm_subject_value', 'subject');
    $query->condition('bm.mailbox_id', $mailbox_id)->condition('bm.mail_state', $mail_state);
    $query->orderBy('bm.changed', 'DESC')->range(0, 100);
    $options = [];
    foreach ($query->execute() as $row) {
      $id = (int) $row->communication_id;
      $options[$id] = trim((string) $row->mail_from) . ' — ' . (trim((string) $row->subject) ?: '(geen onderwerp)');
    }

    $form['mailbox_id'] = ['#type' => 'hidden', '#value' => $mailbox_id];
    $form['mail_state'] = ['#type' => 'hidden', '#value' => $mail_state];
    $form['intro'] = ['#markup' => '<p><strong>Bulkacties</strong><br>Selecteer berichten en voer één actie tegelijk uit. Verwijderen betekent veilig verplaatsen naar Prullenbak.</p>'];
    $form['messages'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Berichten'),
      '#options' => $options,
      '#required' => TRUE,
    ];
    $form['action'] = [
      '#type' => 'select',
      '#title' => $this->t('Actie'),
      '#required' => TRUE,
      '#options' => [
        '' => $this->t('- Kies een actie -'),
        'archive' => $this->t('Archiveren'),
        'trash' => $this->t('Verwijderen naar Prullenbak'),
        'inbox' => $this->t('Verplaatsen naar Postvak IN'),
        'spam' => $this->t('Verplaatsen naar Spam'),
        'link_project' => $this->t('Koppelen aan project'),
        'link_administration' => $this->t('Koppelen aan Administratie'),
        'link_personal' => $this->t('Koppelen aan Privé'),
        'link_junk' => $this->t('Koppelen als Junk en naar Spam'),
      ],
    ];
    $form['project'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Project'),
      '#target_type' => 'node',
      '#selection_settings' => ['target_bundles' => ['brebo_project']],
      '#states' => ['visible' => [':input[name="action"]' => ['value' => 'link_project']]],
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['apply'] = ['#type' => 'submit', '#value' => $this->t('Toepassen'), '#button_type' => 'primary'];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Annuleren'),
      '#url' => Url::fromRoute('brebo_mail_intake.mailbox', ['mailbox_id' => $mailbox_id, 'mail_state' => $mail_state]),
      '#attributes' => ['class' => ['button']],
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $ids = array_values(array_filter(array_map('intval', (array) $form_state->getValue('messages'))));
    if ($ids === []) {
      $form_state->setErrorByName('messages', $this->t('Selecteer minimaal één bericht.'));
    }
    if ($form_state->getValue('action') === 'link_project') {
      $project = $this->entityTypeManager->getStorage('node')->load((int) $form_state->getValue('project'));
      if (!$project instanceof NodeInterface || $project->bundle() !== 'brebo_project' || !$project->access('view')) {
        $form_state->setErrorByName('project', $this->t('Kies een toegankelijk BREBO-project.'));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $mailboxId = (int) $form_state->getValue('mailbox_id');
    $mailState = (string) $form_state->getValue('mail_state');
    $action = (string) $form_state->getValue('action');
    $selected = array_values(array_filter(array_map('intval', (array) $form_state->getValue('messages'))));

    $query = $this->database->select('brebo_mailbox_message', 'bm')->fields('bm', ['communication_id']);
    $query->condition('mailbox_id', $mailboxId)->condition('mail_state', $mailState)->condition('communication_id', $selected, 'IN');
    $ids = array_map('intval', $query->execute()->fetchCol());
    if ($ids === []) {
      $this->messenger()->addWarning($this->t('Geen geldige berichten geselecteerd.'));
      return;
    }

    if (in_array($action, ['archive', 'trash', 'inbox', 'spam'], TRUE)) {
      $this->database->update('brebo_mailbox_message')
        ->fields(['mail_state' => $action, 'changed' => time()])
        ->condition('mailbox_id', $mailboxId)
        ->condition('communication_id', $ids, 'IN')
        ->execute();
      $destination = $action;
    }
    else {
      $mapping = [
        'link_project' => 'Projectgericht',
        'link_administration' => 'Administratie',
        'link_personal' => 'Persoonlijk',
        'link_junk' => 'Junk',
      ];
      if (!isset($mapping[$action])) {
        throw new \InvalidArgumentException('Onbekende bulkactie.');
      }
      $projectId = $action === 'link_project' ? (int) $form_state->getValue('project') : 0;
      foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($ids) as $node) {
        if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_communication' || !$node->access('update', $this->mailCurrentUser)) {
          continue;
        }
        if ($node->hasField('field_brebo_project_ref')) {
          $node->set('field_brebo_project_ref', $projectId ?: NULL);
        }
        if ($node->hasField('field_brebo_building_ref')) {
          $node->set('field_brebo_building_ref', NULL);
        }
        if ($node->hasField('field_brebo_comm_scope_target')) {
          $node->set('field_brebo_comm_scope_target', NULL);
        }
        if ($node->hasField('field_brebo_comm_context')) {
          $node->set('field_brebo_comm_context', $mapping[$action]);
        }
        $node->setNewRevision(TRUE);
        $node->setRevisionLogMessage('Primaire mailbestemming via gecontroleerde bulkactie bevestigd.');
        $node->save();
      }
      if ($action === 'link_junk') {
        $this->database->update('brebo_mailbox_message')
          ->fields(['mail_state' => 'spam', 'is_read' => 1, 'needs_action' => 0, 'changed' => time()])
          ->condition('mailbox_id', $mailboxId)->condition('communication_id', $ids, 'IN')->execute();
        $destination = 'spam';
      }
      else {
        $destination = $mailState;
      }
    }

    $this->messenger()->addStatus($this->formatPlural(count($ids), '1 bericht verwerkt.', '@count berichten verwerkt.'));
    $form_state->setRedirect('brebo_mail_intake.mailbox', ['mailbox_id' => $mailboxId, 'mail_state' => $destination]);
  }

}
