<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Controller;

use Drupal\brebo_mail_intake\Form\MailboxMessageActionForm;
use Drupal\brebo_mail_intake\Form\MailboxTagForm;
use Drupal\brebo_mail_intake\Service\MailEditorProvisioner;
use Drupal\brebo_mail_intake\Service\MailboxAccessPolicy;
use Drupal\brebo_mail_intake\Service\MailboxRepository;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Provides the three-pane BREBO mailbox workspace. */
final class MailboxController extends ControllerBase {

  private const PAGE_SIZE = 50;

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
    private readonly MailEditorProvisioner $editorProvisioner,
    private readonly RequestStack $mailboxRequestStack,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_mail_intake.mailbox_repository'),
      $container->get('brebo_mail_intake.mailbox_access_policy'),
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('brebo_mail_intake.editor_provisioner'),
      $container->get('request_stack'),
    );
  }

  public function page(int $mailbox_id = 0, string $mail_state = 'inbox', int $communication_id = 0): array {
    $this->editorProvisioner->ensure();
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

    $request = $this->mailboxRequestStack->getCurrentRequest();
    $page = max(0, (int) ($request?->query->get('mail_page') ?? 0));
    $messagePage = $this->messageRows($mailbox_id, $mail_state, $page);
    $messages = $messagePage['rows'];
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
      'messages' => $this->messagePane($messages, $mailbox_id, $mail_state, $communication_id, $page, $messagePage['has_next']),
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

  private function messagePane(array $messages, int $mailboxId, string $state, int $selectedId, int $page, bool $hasNext): array {
    if ($messages === []) {
      $empty = $page > 0
        ? 'Geen berichten op deze pagina.'
        : 'Geen berichten in ' . self::STATES[$state] . '.';
      $build = [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-mail-list']],
        'empty' => ['#markup' => '<p class="brebo-mail-list__empty">' . htmlspecialchars($empty, ENT_QUOTES, 'UTF-8') . '</p>'],
      ];
      if ($page > 0) {
        $build['pager'] = $this->mailPager($mailboxId, $state, $page, FALSE);
      }
      return $build;
    }

    $items = [];
    foreach ($messages as $row) {
      $id = (int) $row['communication_id'];
      $url = Url::fromRoute('brebo_mail_intake.mailbox_message', [
        'mailbox_id' => $mailboxId,
        'mail_state' => $state,
        'communication_id' => $id,
      ], ['query' => ['mail_page' => $page]]);
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

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-mail-list']],
      'page_info' => ['#markup' => '<div class="brebo-mail-list__page-info">Pagina ' . ($page + 1) . ' · maximaal ' . self::PAGE_SIZE . ' berichten</div>'],
      'items' => $items,
      'pager' => $this->mailPager($mailboxId, $state, $page, $hasNext),
    ];
  }

  private function mailPager(int $mailboxId, string $state, int $page, bool $hasNext): array {
    $links = [];
    if ($page > 0) {
      $links['previous'] = [
        '#type' => 'link',
        '#title' => '← Nieuwere berichten',
        '#url' => Url::fromRoute('brebo_mail_intake.mailbox', [
          'mailbox_id' => $mailboxId,
          'mail_state' => $state,
        ], ['query' => ['mail_page' => $page - 1]]),
        '#prefix' => '<span class="brebo-mail-pager__previous">',
        '#suffix' => '</span>',
      ];
    }
    if ($hasNext) {
      $links['next'] = [
        '#type' => 'link',
        '#title' => 'Oudere berichten →',
        '#url' => Url::fromRoute('brebo_mail_intake.mailbox', [
          'mailbox_id' => $mailboxId,
          'mail_state' => $state,
        ], ['query' => ['mail_page' => $page + 1]]),
        '#prefix' => '<span class="brebo-mail-pager__next">',
        '#suffix' => '</span>',
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-mail-pager']],
      'links' => $links,
    ];
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

    $contextLabel = trim((string) ($message['context_label'] ?? ''));
    $projectLabel = trim((string) ($message['project_label'] ?? ''));
    $buildingLabel = trim((string) ($message['building_label'] ?? ''));
    $objectContext = [];
    if ($projectLabel !== '') {
      $objectContext[] = 'Project: ' . htmlspecialchars($projectLabel, ENT_QUOTES, 'UTF-8');
    }
    if ($buildingLabel !== '') {
      $objectContext[] = 'Gebouw: ' . htmlspecialchars($buildingLabel, ENT_QUOTES, 'UTF-8');
    }

    if ($objectContext !== []) {
      $contextMarkup = '<div class="brebo-mail-office-link-status"><strong>🔗 Gekoppeld in Office</strong><span>' . implode(' · ', $objectContext) . '</span></div>';
    }
    elseif (in_array($contextLabel, ['Administratie', 'Persoonlijk'], TRUE)) {
      $contextMarkup = '<div class="brebo-mail-office-link-status"><strong>✓ Ingedeeld in Office</strong><span>Bestemming: ' . htmlspecialchars($contextLabel, ENT_QUOTES, 'UTF-8') . '</span></div>';
    }
    else {
      $classification = $contextLabel !== ''
        ? 'Classificatie: ' . htmlspecialchars($contextLabel, ENT_QUOTES, 'UTF-8') . ' · '
        : '';
      $contextMarkup = '<div class="brebo-mail-office-link-status is-unlinked"><span>' . $classification . 'Nog niet aan een bestemming gekoppeld</span></div>';
    }

    $attachmentMarkup = '';
    foreach (($message['attachments'] ?? []) as $attachment) {
      $attachmentTitle = htmlspecialchars((string) ($attachment['title'] ?? ''), ENT_QUOTES, 'UTF-8');
      $documentId = (int) ($attachment['document_id'] ?? 0);
      if ($documentId > 0) {
        $detailUrl = Url::fromRoute('brebo_document_data.document_detail', ['document_id' => $documentId])->toString();
        $role = htmlspecialchars((string) ($attachment['role_label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $attachmentMarkup .= '<li><a href="' . htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') . '">' . $attachmentTitle . '</a>' . ($role !== '' ? ' <small>(' . $role . ')</small>' : '') . '</li>';
      }
      else {
        $attachmentMarkup .= '<li>' . $attachmentTitle . '</li>';
      }
    }
    if ($attachmentMarkup !== '') {
      $attachmentMarkup = '<div class="brebo-mail-reader__attachments"><strong>📎 Documenten en bijlagen</strong><ul>' . $attachmentMarkup . '</ul></div>';
    }

    $tagMarkup = '';
    foreach (($message['tags'] ?? []) as $tag) {
      $tagMarkup .= '<span class="brebo-mail-tag">' . htmlspecialchars((string) $tag, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    $header = '<h2>' . $title . '</h2><div class="brebo-mail-reader__meta"><strong>Van:</strong> ' . $from . '<br><strong>Aan:</strong> ' . $to . '<br>' . ($cc !== '' ? '<strong>CC:</strong> ' . $cc . '<br>' : '') . '<strong>Datum/tijd:</strong> ' . $date . '</div>' . $contextMarkup . ($tagMarkup !== '' ? '<div class="brebo-mail-tag-list brebo-mail-tag-list--reader">' . $tagMarkup . '</div>' : '') . $attachmentMarkup;

    $htmlBody = trim((string) ($message['mail_html'] ?? ''));
    $body = $htmlBody !== ''
      ? [
        '#type' => 'processed_text',
        '#text' => $this->displayableHtml($htmlBody),
        '#format' => 'brebo_mail_html',
        '#prefix' => '<div class="brebo-mail-reader__body brebo-mail-reader__body--html">',
        '#suffix' => '</div>',
      ]
      : [
        '#markup' => '<div class="brebo-mail-reader__body">' . nl2br(htmlspecialchars((string) ($message['transcript'] ?? ''), ENT_QUOTES, 'UTF-8')) . '</div>',
      ];

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-mail-reader']],
      'content' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-mail-reader__article']],
        'header' => ['#markup' => $header],
        'body' => $body,
      ],
    ];

    if ($communicationId > 0) {
      $build['tags_form'] = $this->formBuilder()->getForm(MailboxTagForm::class, $mailboxId, $communicationId, $mailState);
    }
    return $build;
  }

  private function displayableHtml(string $html): string {
    $html = preg_replace('/<(?:script|style|head)\b[^>]*>.*?<\/(?:script|style|head)>/isu', '', $html) ?? $html;
    $html = preg_replace('/<!doctype[^>]*>/iu', '', $html) ?? $html;
    if (preg_match('/<body\b[^>]*>(.*)<\/body>/isu', $html, $matches) === 1) {
      $html = (string) $matches[1];
    }
    return trim($html);
  }

  /** @return array{rows: array<int, array<string, mixed>>, has_next: bool} */
  private function messageRows(int $mailboxId, string $state, int $page): array {
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
    $query->orderBy('md.field_brebo_comm_datetime_value', 'DESC')->orderBy('bm.changed', 'DESC');
    $query->range($page * self::PAGE_SIZE, self::PAGE_SIZE + 1);
    $rows = array_values(array_map('get_object_vars', $query->execute()->fetchAll()));
    $hasNext = count($rows) > self::PAGE_SIZE;
    if ($hasNext) {
      array_pop($rows);
    }
    if ($rows === []) {
      return ['rows' => [], 'has_next' => FALSE];
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
      $contextLabel = ($node && $node->hasField('field_brebo_comm_context')) ? trim((string) $node->get('field_brebo_comm_context')->value) : '';
      $row['office_linked'] = $projectId > 0 || $buildingId > 0 || in_array($contextLabel, ['Administratie', 'Persoonlijk'], TRUE);
      $row['tags'] = $tagsByCommunication[$id] ?? [];
    }
    unset($row);

    return ['rows' => $rows, 'has_next' => $hasNext];
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

    $fileAttachments = [];
    if ($node->hasField('field_brebo_comm_attachments')) {
      foreach ($node->get('field_brebo_comm_attachments')->referencedEntities() as $file) {
        $fileAttachments[] = [
          'title' => (string) $file->label(),
          'document_id' => 0,
          'role_label' => '',
        ];
      }
    }

    $documentAttachments = [];
    $canonicalTitles = [];
    if ($this->database->schema()->tableExists('brebo_document_communication')) {
      $documentQuery = $this->database->select('brebo_document_communication', 'dc');
      $documentQuery->join('brebo_document', 'd', 'd.id = dc.document_id');
      $documentQuery->fields('dc', ['document_id', 'relation_role']);
      $documentQuery->fields('d', ['title', 'original_filename']);
      $documentQuery->condition('dc.communication_nid', $communicationId)
        ->condition('d.lifecycle_status', 'deleted', '<>')
        ->orderBy('dc.created')
        ->orderBy('dc.id');
      $roleLabels = [
        'received_with' => 'ontvangen via deze mail',
        'sent_with' => 'meegestuurd met deze mail',
        'created_with' => 'aangemaakt bij deze mail',
      ];
      $seenDocuments = [];
      foreach ($documentQuery->execute() as $documentRow) {
        $documentId = (int) $documentRow->document_id;
        if (isset($seenDocuments[$documentId])) {
          continue;
        }
        $seenDocuments[$documentId] = TRUE;
        $documentTitle = trim((string) ($documentRow->original_filename ?: $documentRow->title));
        $canonicalTitles[mb_strtolower($documentTitle)] = TRUE;
        $documentAttachments[] = [
          'title' => $documentTitle,
          'document_id' => $documentId,
          'role_label' => $roleLabels[(string) $documentRow->relation_role] ?? '',
        ];
      }
    }

    $attachments = $documentAttachments;
    foreach ($fileAttachments as $fileAttachment) {
      if (!isset($canonicalTitles[mb_strtolower((string) $fileAttachment['title'])])) {
        $attachments[] = $fileAttachment;
      }
    }

    return [
      'title' => $node->label(),
      'mail_from' => $node->hasField('field_brebo_mail_from') ? (string) $node->get('field_brebo_mail_from')->value : '',
      'mail_to' => $node->hasField('field_brebo_mail_to') ? (string) $node->get('field_brebo_mail_to')->value : '',
      'mail_cc' => $node->hasField('field_brebo_mail_cc') ? (string) $node->get('field_brebo_mail_cc')->value : '',
      'mail_datetime' => $node->hasField('field_brebo_comm_datetime') ? (string) $node->get('field_brebo_comm_datetime')->value : '',
      'transcript' => $node->hasField('field_brebo_transcript') ? (string) $node->get('field_brebo_transcript')->value : '',
      'mail_html' => $node->hasField('field_brebo_mail_html') ? (string) $node->get('field_brebo_mail_html')->value : '',
      'context_label' => $node->hasField('field_brebo_comm_context') ? trim((string) $node->get('field_brebo_comm_context')->value) : '',
      'project_label' => $project ? $project->label() : '',
      'building_label' => $building ? $building->label() : '',
      'tags' => $tags,
      'attachments' => $attachments,
    ];
  }

}
