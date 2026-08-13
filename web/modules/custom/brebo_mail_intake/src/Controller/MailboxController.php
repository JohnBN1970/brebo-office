<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Controller;

use Drupal\brebo_mail_intake\Service\MailboxAccessPolicy;
use Drupal\brebo_mail_intake\Service\MailboxRepository;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Read-only three-pane BREBO mailbox workspace. */
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
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
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
      fn(array $mailbox): bool => !empty($mailbox['active']) && $this->accessPolicy->allowed($this->currentUser, (int) $mailbox['id'], 'view'),
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
    if (!$this->accessPolicy->allowed($this->currentUser, $mailbox_id, 'view')) {
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

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-mail-workspace']],
      'heading' => [
        '#markup' => '<h1>Mail</h1><p><strong>' . htmlspecialchars((string) $mailbox['label'], ENT_QUOTES, 'UTF-8') . '</strong>' . (($mailbox['address'] ?? '') !== '' ? ' &lt;' . htmlspecialchars((string) $mailbox['address'], ENT_QUOTES, 'UTF-8') . '&gt;' : '') . '</p>',
      ],
      'layout' => [
        '#type' => 'container',
        '#attributes' => ['style' => 'display:grid;grid-template-columns:minmax(190px,1fr) minmax(320px,2fr) minmax(360px,3fr);gap:1rem;align-items:start;'],
        'folders' => $this->folderPane($visibleMailboxes, $mailbox_id, $mail_state),
        'messages' => $this->messagePane($messages, $mailbox_id, $mail_state, $communication_id),
        'reader' => $this->readerPane($selected),
      ],
      '#cache' => ['max-age' => 0],
    ];
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
      return ['#type' => 'container', 'empty' => ['#markup' => '<p>Geen berichten in ' . htmlspecialchars(self::STATES[$state], ENT_QUOTES, 'UTF-8') . '.</p>']];
    }

    $items = [];
    foreach ($messages as $row) {
      $id = (int) $row['communication_id'];
      $url = Url::fromRoute('brebo_mail_intake.mailbox_message', ['mailbox_id' => $mailboxId, 'mail_state' => $state, 'communication_id' => $id]);
      $subject = trim((string) ($row['subject'] ?? '')) ?: '(geen onderwerp)';
      $from = trim((string) ($row['mail_from'] ?? '')) ?: 'Onbekende afzender';
      $date = trim((string) ($row['mail_datetime'] ?? ''));
      $flags = (!empty($row['is_starred']) ? '★ ' : '') . (empty($row['is_read']) ? '● ' : '') . (!empty($row['needs_action']) ? '⚑ ' : '');
      $items[] = [
        '#type' => 'link',
        '#title' => $flags . $from . ' — ' . $subject . ($date !== '' ? ' · ' . $date : ''),
        '#url' => $url,
        '#prefix' => '<div style="padding:.65rem;border-bottom:1px solid #ddd;' . ($id === $selectedId ? 'font-weight:700;' : '') . '">',
        '#suffix' => '</div>',
      ];
    }
    return ['#type' => 'container', '#attributes' => ['class' => ['brebo-mail-list']], 'items' => $items];
  }

  private function readerPane(?array $message): array {
    if (!$message) {
      return ['#type' => 'container', 'empty' => ['#markup' => '<p>Selecteer een bericht.</p>']];
    }

    $title = htmlspecialchars((string) ($message['title'] ?? '(geen onderwerp)'), ENT_QUOTES, 'UTF-8');
    $from = htmlspecialchars((string) ($message['mail_from'] ?? ''), ENT_QUOTES, 'UTF-8');
    $to = htmlspecialchars((string) ($message['mail_to'] ?? ''), ENT_QUOTES, 'UTF-8');
    $date = htmlspecialchars((string) ($message['mail_datetime'] ?? ''), ENT_QUOTES, 'UTF-8');
    $body = nl2br(htmlspecialchars((string) ($message['transcript'] ?? ''), ENT_QUOTES, 'UTF-8'));

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-mail-reader']],
      'content' => ['#markup' => '<article><h2>' . $title . '</h2><p><strong>Van:</strong> ' . $from . '<br><strong>Aan:</strong> ' . $to . '<br><strong>Datum/tijd:</strong> ' . $date . '</p><hr><div>' . $body . '</div></article>'],
    ];
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
    return array_values(array_map('get_object_vars', $query->execute()->fetchAll()));
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

    $node = $this->entityTypeManager->getStorage('node')->load($communicationId);
    if (!$node || $node->bundle() !== 'brebo_communication' || !$node->access('view', $this->currentUser)) {
      return NULL;
    }

    return [
      'title' => $node->label(),
      'mail_from' => $node->hasField('field_brebo_mail_from') ? (string) $node->get('field_brebo_mail_from')->value : '',
      'mail_to' => $node->hasField('field_brebo_mail_to') ? (string) $node->get('field_brebo_mail_to')->value : '',
      'mail_datetime' => $node->hasField('field_brebo_comm_datetime') ? (string) $node->get('field_brebo_comm_datetime')->value : '',
      'transcript' => $node->hasField('field_brebo_transcript') ? (string) $node->get('field_brebo_transcript')->value : '',
    ];
  }

}
