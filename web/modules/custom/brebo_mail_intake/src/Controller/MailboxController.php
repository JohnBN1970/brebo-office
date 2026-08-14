<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Controller;

use Drupal\brebo_mail_intake\Form\MailboxMessageActionForm;
use Drupal\brebo_mail_intake\Form\MailboxTagForm;
use Drupal\brebo_mail_intake\Service\MailboxAccessPolicy;
use Drupal\brebo_mail_intake\Service\MailboxRepository;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Provides the three-pane BREBO mailbox workspace. */
final class MailboxController extends ControllerBase {

  private const STATES = [
    'inbox' => 'Postvak IN',
    'sent' => 'Verzonden',
    'draft' => 'Concepten',
    'spam' => 'Spam',
    'archive' => 'Archief',
    'trash' => 'Prullenbak',
  ];

  public function __construct(
    private readonly MailboxRepository $mailboxes,
    private readonly MailboxAccessPolicy $accessPolicy,
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $mailboxEntityTypeManager,
    private readonly AccountProxyInterface $mailboxCurrentUser,
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

  public function page(int $mailbox_id = 0, string $mail_state = 'inbox', int $communication_id = 0): array {
    $visibleMailboxes = array_values(array_filter(
      $this->mailboxes->all(),
      fn(array $mailbox): bool => !empty($mailbox['active']) && $this->accessPolicy->allowed($this->mailboxCurrentUser, (int) $mailbox['id'], 'view'),
    ));

    if ($visibleMailboxes === []) {
      return [
        '#type' => 'container',
        'empty' => ['#markup' => '<p>Voor uw rollen zijn nog geen BREBO-mailboxen beschikbaar.</p>'],
        '#cache' => ['max-age' => 0],
      ];
    }

    if ($mailbox_id <= 0) {
      $mailbox_id = (int) $visibleMailboxes[0]['id'];
    }
    $mailbox = $this->mailboxes->load($mailbox_id);
    if (!$mailbox) {
      throw new NotFoundHttpException();
    }
    if (!$this->accessPolicy->allowed($this->mailboxCurrentUser, $mailbox_id, 'view')) {
      throw new AccessDeniedHttpException();
    }
    if (!isset(self::STATES[$mail_state])) {
      $mail_state = 'inbox';
    }

    $messages = $this->messageRows($mailbox_id, $mail_state);
    if ($communication_id <= 0 && $messages !== []) {
      $communication_id = (int) $messages[0]['communication_id'];
    }
    $selected = $communication_id > 0 ? $this->loadCommunication($mailbox_id, $communication_id) : NULL;

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-mail-workspace']],
      '#cache' => ['max-age' => 0],
    ];

    if ($selected && $communication_id > 0) {
      $build['state_actions'] = $this->formBuilder()->getForm(
        MailboxMessageActionForm::class,
        $mailbox_id,
        $communication_id,
        $mail_state,
      );
    }

    $build['layout'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-mail-layout']],
      'folders' => $this->folderPane($visibleMailboxes, $mailbox_id, $mail_state),
      'messages' => $this->messagePane($messages, $mailbox_id, $mail_state, $communication_id),
      'reader' => $this->readerPane($selected, $mailbox_id, $communication_id, $mail_state),
    ];

    return $build;
  }

  private function folderPane(array $mailboxes, int $selectedMailbox, string $selectedState): array {
    $items = [];
    foreach ($mailboxes as $mailbox) {
      $id = (int) $mailbox['id'];
      $items[] = ['#markup' => '<div style="margin-top:.75rem"><strong>' . htmlspecialchars((string) $mailbox['label'], ENT_QUOTES, 'UTF-8') . '</strong></div>'];
      foreach (self::STATES as $state => $label) {
        $url = Url::fromRoute('brebo_mail_intake.mailbox', ['mailbox_id' => $id, 'mail_state' => $state]);
        $active = $id === $selectedMailbox && $state === $selectedState;
        $items[] = [
          '#type' => 'link',
          '#title' => $label,
          '#url' => $url,
          '#prefix' => '<div style="padding:.2rem 0;' . ($active ? 'font-weight:700;' : '') . '">',
          '#suffix' => '</div>',
        ];
      }
    }
    return ['#type' => 'container', '#attributes' => ['class' => ['brebo-mail-folders']], 'items' => $items];
  }

  private function messagePane(array $messages, int $mailboxId, string $state, int $selectedId): array {
    if ($messages === []) {
      return ['#type' => 'container', '#attributes' => ['class' => ['brebo-mail-list']], 'empty' => ['#markup' => '<p class="brebo-mail-list__empty">Geen berichten in ' . htmlspecialchars(self::STATES[$state], ENT_QUOTES, 'UTF-8') . '.</p>']];
    }

    $items = [];
    foreach ($messages as $row) {
      $id = (int) $row['communication_id'];
      $url = Url::fromRoute('brebo_mail_intake.mailbox_message', ['mailbox_id' => $mailboxId, 'mail_state' => $state, 'communication_id' => $id]);
      $subject = trim((string) ($row['subject'] ?? '')) ?: '(geen onderwerp)';
      $from = trim((string) ($row['mail_from'] ?? '')) ?: 'Onbekende afzender';
      $date = trim((string) ($row['mail_datetime'] ?? ''));
      $linked = !empty($row['office_linked']);
      $flags = (!empty($row['is_starred']) ? '★ ' : '') . (empty($row['is_read']) ? '● ' : '') . (!empty($row['needs_action']) ? '⚑ ' : '') . ($linked ? '🔗 ' : '');
      $tagMarkup = '';
      foreach (($row['tags'] ?? []) as $tag) {
        $tagMarkup .= '<span class="brebo-mail-tag">' . htmlspecialchars((string) $tag, ENT_QUOTES, 'UTF-8') . '</span>';
      }
      $items[] = [
        '#type' => 'link',
        '#title' => $flags . $from . ' — ' . $subject . ($date !== '' ? ' · ' . $date : ''),
        '#url' => $url,
        '#attributes' => [
          'class' => $linked ? ['brebo-mail-message-link', 'is-office-linked'] : ['brebo-mail-message-link'],
          'title' => $linked ? 'Al gekoppeld aan BREBO Office' : '',
        ],
        '#prefix' => '<div class="brebo-mail-message-row" style="padding:.65rem;border-bottom:1px solid #ddd;' . ($id === $selectedId ? 'font-weight:700;' : '') . '">',
        '#suffix' => ($tagMarkup !== '' ? '<div class="brebo-mail-tag-list">' . $tagMarkup . '</div>' : '') . '</div>',
      ];
    }
    return ['#type' => 'container', '#attributes' => ['class' => ['brebo-mail-list']], 'items' => $items];
  }

  private function readerPane(?array $message, int $mailboxId, int $communicationId, string $mailState): array {
    if (!$message) {
      return ['#type' => 'container', '#attributes' => ['class' => ['brebo-mail-reader']], 'empty' => ['#markup' => '<p class="brebo-mail-reader__empty">Selecteer een bericht.</p>']];
    }

    $title = htmlspecialchars((string) ($message['title'] ?? '(geen onderwerp)'), ENT_QUOTES, 'UTF-8');
    $from = htmlspecialchars((string) ($message['mail_from'] ?? ''), ENT_QUOTES, 'UTF-8');
    $to = htmlspecialchars((string) ($message['mail_to'] ?? ''), ENT_QUOTES, 'UTF-8');
    $cc = htmlspecialchars((string) ($message['mail_cc'] ?? ''), ENT_QUOTES, 'UTF-8');
    $date = htmlspecialchars((string) ($message['mail_datetime'] ?? ''), ENT_QUOTES, 'UTF-8');
    $htmlBody = trim((string) ($message['mail_html'] ?? ''));
    $body = $htmlBody !== ''
      ? Xss::filter($htmlBody, [
        'a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'code', 'col', 'colgroup',
        'dd', 'del', 'div', 'dl', 'dt', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'hr', 'i', 'ins', 'li', 'ol', 'p', 'pre', 's', 'small', 'span', 'strong',
        'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul',
      ])
      : nl2br(htmlspecialchars((string) ($message['transcript'] ?? ''), ENT_QUOTES, 'UTF-8'));

    $context = [];
    if (($message['project_label'] ?? '') !== '') {
      $context[] = 'Project: ' . htmlspecialchars((string) $message['project_label'], ENT_QUOTES, 'UTF-8');
    }
    if (($message['building_label'] ?? '') !== '') {
      $context[] = 'Gebouw: ' . htmlspecialchars((string) $message['building_label'], ENT_QUOTES, 'UTF-8');
    }
    $contextMarkup = $context
      ? '<div class="brebo-mail-office-link-status"><strong>🔗 Gekoppeld in Office</strong><span>' . implode(' · ', $context) . '</span></div>'
      : '<div class="brebo-mail-office-link-status is-unlinked"><span>Nog niet gekoppeld aan project of gebouw</span></div>';

    $attachmentMarkup = '';
    foreach (($message['attachments'] ?? []) as $attachment) {
      $attachmentMarkup .= '<li>' . htmlspecialchars((string) $attachment, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    if ($attachmentMarkup !== '') {
      $attachmentMarkup = '<div class="brebo-mail-reader__attachments"><strong>📎 Bijlagen</strong><ul>' . $attachmentMarkup . '</ul></div>';
    }

    $tagMarkup = '';
    foreach (($message['tags'] ?? []) as $tag) {
      $tagMarkup .= '<span class="brebo-mail-tag">' . htmlspecialchars((string) $tag, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-mail-reader']],
      'content' => ['#markup' => '<article><h2>' . $title . '</h2><div class="brebo-mail-reader__meta"><strong>Van:</strong> ' . $from . '<br><strong>Aan:</strong> ' . $to . '<br>' . ($cc !== '' ? '<strong>CC:</strong> ' . $cc . '<br>' : '') . '<strong>Datum/tijd:</strong> ' . $date . '</div>' . $contextMarkup . ($tagMarkup !== '' ? '<div class="brebo-mail-tag-list brebo-mail-tag-list--reader">' . $tagMarkup . '</div>' : '') . $attachmentMarkup . '<div class="brebo-mail-reader__body">' . $body . '</div></article>'],
    ];

    if ($communicationId > 0) {
      $build['tags_form'] = $this->formBuilder()->getForm(MailboxTagForm::class, $mailboxId, $communicationId, $mailState);
    }
    return $build;
  }

  /** @return array<int, array<string, mixed>> */
  private function messageRows(int $mailboxId, string $state): array {
    $query = $this->database->select('brebo_mailbox_message', 'bm');
    $query->join('node_field_data', 'n', 'n.nid = bm.communication_id AND n.default_langcode = 1');
    $query->leftJoin('node__field_brebo_mail_from', 'mf', 'mf.entity_id = n.nid AND mf.deleted = 0');
    $query->leftJoin('node__field_brebo_comm_subject', 'ms', 'ms.entity_id = n.nid AND ms.deleted = 0');
    $query->leftJoin('node__field_brebo_comm_datetime', 'md', 'md.entity_id = n.nid AND md.deleted = 0');
    $query->fields('bm', ['communication_id', 'is_read', 'is_starred', 'needs_action', 'changed']);
    $query->addField('mf', 'field_brebo_mail_from_value', 'mail_from');
    $query->addField('ms', 'field_brebo_comm_subject_value', 'subject');
    $query->addField('md', 'field_brebo_comm_datetime_value', 'mail_datetime');
    $query->condition('bm.mailbox_id', $mailboxId)->condition('bm.mail_state', $state)->condition('n.type', 'brebo_communication');
    $query->orderBy('md.field_brebo_comm_datetime_value', 'DESC')->orderBy('bm.changed', 'DESC')->range(0, 100);
    $rows = array_values(array_map('get_object_vars', $query->execute()->fetchAll()));
    if ($rows === []) {
      return [];
    }

    $ids = array_map(static fn(array $row): int => (int) $row['communication_id'], $rows);
    $nodes = $this->mailboxEntityTypeManager->getStorage('node')->loadMultiple($ids);
    $tagsByCommunication = [];
    if ($this->database->schema()->tableExists('brebo_mail_tag')) {
      $tagQuery = $this->database->select('brebo_mail_tag', 't')->fields('t', ['communication_id', 'tag']);
      $tagQuery->condition('communication_id', $ids, 'IN')->orderBy('tag');
      foreach ($tagQuery->execute() as $tagRow) {
        $tagsByCommunication[(int) $tagRow->communication_id][] = (string) $tagRow->tag;
      }
    }

    foreach ($rows as &$row) {
      $id = (int) $row['communication_id'];
      $node = $nodes[$id] ?? NULL;
      $projectId = ($node && $node->hasField('field_brebo_project_ref')) ? (int) ($node->get('field_brebo_project_ref')->target_id ?? 0) : 0;
      $buildingId = ($node && $node->hasField('field_brebo_building_ref')) ? (int) ($node->get('field_brebo_building_ref')->target_id ?? 0) : 0;
      $row['office_linked'] = $projectId > 0 || $buildingId > 0;
      $row['tags'] = $tagsByCommunication[$id] ?? [];
    }
    unset($row);

    return $rows;
  }

  /** @return array<string, mixed>|null */
  private function loadCommunication(int $mailboxId, int $communicationId): ?array {
    $membership = $this->database->select('brebo_mailbox_message', 'bm')
      ->fields('bm', ['communication_id'])
      ->condition('mailbox_id', $mailboxId)
      ->condition('communication_id', $communicationId)
      ->range(0, 1)->execute()->fetchField();
    if (!$membership) {
      return NULL;
    }

    $node = $this->mailboxEntityTypeManager->getStorage('node')->load($communicationId);
    if (!$node || $node->bundle() !== 'brebo_communication' || !$node->access('view', $this->mailboxCurrentUser)) {
      return NULL;
    }

    $project = $node->hasField('field_brebo_project_ref') ? $node->get('field_brebo_project_ref')->entity : NULL;
    $building = $node->hasField('field_brebo_building_ref') ? $node->get('field_brebo_building_ref')->entity : NULL;
    $tags = [];
    if ($this->database->schema()->tableExists('brebo_mail_tag')) {
      $tags = $this->database->select('brebo_mail_tag', 't')
        ->fields('t', ['tag'])
        ->condition('communication_id', $communicationId)
        ->orderBy('tag')
        ->execute()
        ->fetchCol();
    }

    $attachments = [];
    if ($node->hasField('field_brebo_comm_attachments')) {
      foreach ($node->get('field_brebo_comm_attachments')->referencedEntities() as $file) {
        $attachments[] = (string) $file->label();
      }
    }
    if ($this->database->schema()->tableExists('brebo_outbound_document_attachment')) {
      $documentQuery = $this->database->select('brebo_outbound_document_attachment', 'a');
      $documentQuery->join('brebo_document', 'd', 'd.id = a.document_id');
      $documentQuery->addField('d', 'title');
      $documentQuery->condition('a.communication_id', $communicationId)->condition('d.lifecycle_status', 'deleted', '<>');
      $attachments = array_merge($attachments, array_map('strval', $documentQuery->execute()->fetchCol()));
    }

    return [
      'title' => $node->label(),
      'mail_from' => $node->hasField('field_brebo_mail_from') ? (string) $node->get('field_brebo_mail_from')->value : '',
      'mail_to' => $node->hasField('field_brebo_mail_to') ? (string) $node->get('field_brebo_mail_to')->value : '',
      'mail_cc' => $node->hasField('field_brebo_mail_cc') ? (string) $node->get('field_brebo_mail_cc')->value : '',
      'mail_datetime' => $node->hasField('field_brebo_comm_datetime') ? (string) $node->get('field_brebo_comm_datetime')->value : '',
      'transcript' => $node->hasField('field_brebo_transcript') ? (string) $node->get('field_brebo_transcript')->value : '',
      'mail_html' => $node->hasField('field_brebo_mail_html') ? (string) $node->get('field_brebo_mail_html')->value : '',
      'project_label' => $project ? $project->label() : '',
      'building_label' => $building ? $building->label() : '',
      'tags' => $tags,
      'attachments' => array_values(array_unique($attachments)),
    ];
  }

}
