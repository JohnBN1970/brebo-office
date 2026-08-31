<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Controller;

use Drupal\brebo_mail_intake\Service\MailboxAccessPolicy;
use Drupal\brebo_mail_intake\Service\MailboxRepository;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Provides privacy-aware search across BREBO mailboxes visible to the user. */
final class MailSearchController extends ControllerBase {

  private const STATES = [
    '' => 'Alle mappen',
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
    private readonly AccountProxyInterface $currentUser,
    private readonly RequestStack $requestStack,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_mail_intake.mailbox_repository'),
      $container->get('brebo_mail_intake.mailbox_access_policy'),
      $container->get('database'),
      $container->get('current_user'),
      $container->get('request_stack'),
    );
  }

  public function page(): array {
    $visible = array_values(array_filter(
      $this->mailboxes->all(),
      fn(array $mailbox): bool => !empty($mailbox['active']) && $this->accessPolicy->allowed($this->currentUser, (int) $mailbox['id'], 'view'),
    ));

    if ($visible === []) {
      return [
        '#markup' => '<p>Voor uw rollen zijn geen BREBO-mailboxen beschikbaar.</p>',
        '#cache' => ['max-age' => 0],
      ];
    }

    $request = $this->requestStack->getCurrentRequest();
    $term = trim((string) ($request?->query->get('q') ?? ''));
    $mailboxId = max(0, (int) ($request?->query->get('mailbox') ?? 0));
    $state = trim((string) ($request?->query->get('state') ?? ''));
    if (!array_key_exists($state, self::STATES)) {
      $state = '';
    }

    $visibleById = [];
    foreach ($visible as $mailbox) {
      $visibleById[(int) $mailbox['id']] = $mailbox;
    }
    if ($mailboxId > 0 && !isset($visibleById[$mailboxId])) {
      $mailboxId = 0;
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-mail-search']],
      '#cache' => ['max-age' => 0],
      'form' => ['#markup' => $this->searchFormMarkup($visible, $term, $mailboxId, $state)],
    ];

    if ($term === '') {
      $build['intro'] = ['#markup' => '<p>Zoek op onderwerp, afzender, ontvanger of tekst in de mail. Alleen mailboxen waarvoor u toegang hebt worden doorzocht.</p>'];
      return $build;
    }

    $rows = $this->searchRows(array_keys($visibleById), $term, $mailboxId, $state);
    if ($rows === []) {
      $build['results'] = ['#markup' => '<p>Geen mail gevonden voor deze zoekopdracht.</p>'];
      return $build;
    }

    $tableRows = [];
    foreach ($rows as $row) {
      $id = (int) $row['communication_id'];
      $boxId = (int) $row['mailbox_id'];
      $mailState = (string) $row['mail_state'];
      $url = Url::fromRoute('brebo_mail_intake.mailbox_message', [
        'mailbox_id' => $boxId,
        'mail_state' => $mailState,
        'communication_id' => $id,
      ]);
      $tableRows[] = [
        'data' => [
          ['data' => ['#type' => 'link', '#title' => trim((string) $row['subject']) ?: '(geen onderwerp)', '#url' => $url]],
          htmlspecialchars((string) $row['mailbox_label'], ENT_QUOTES, 'UTF-8'),
          htmlspecialchars(self::STATES[$mailState] ?? $mailState, ENT_QUOTES, 'UTF-8'),
          htmlspecialchars(trim((string) $row['mail_from']) ?: 'Onbekende afzender', ENT_QUOTES, 'UTF-8'),
          htmlspecialchars((string) $row['mail_datetime'], ENT_QUOTES, 'UTF-8'),
        ],
      ];
    }

    $build['count'] = ['#markup' => '<p><strong>' . count($tableRows) . '</strong> resultaat/resultaten (maximaal 100).</p>'];
    $build['results'] = [
      '#type' => 'table',
      '#header' => ['Onderwerp', 'Mailbox', 'Map', 'Van', 'Datum/tijd'],
      '#rows' => $tableRows,
      '#empty' => 'Geen mail gevonden.',
    ];

    return $build;
  }

  /** @param array<int, array<string, mixed>> $mailboxes */
  private function searchFormMarkup(array $mailboxes, string $term, int $mailboxId, string $state): string {
    $mailboxOptions = '<option value="0">Alle mailboxen</option>';
    foreach ($mailboxes as $mailbox) {
      $id = (int) $mailbox['id'];
      $selected = $id === $mailboxId ? ' selected' : '';
      $mailboxOptions .= '<option value="' . $id . '"' . $selected . '>' . htmlspecialchars((string) $mailbox['label'], ENT_QUOTES, 'UTF-8') . '</option>';
    }

    $stateOptions = '';
    foreach (self::STATES as $value => $label) {
      $selected = $value === $state ? ' selected' : '';
      $stateOptions .= '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }

    return '<form method="get" class="brebo-mail-search__form">'
      . '<label for="brebo-mail-search-q"><strong>Zoeken in mail</strong></label> '
      . '<input id="brebo-mail-search-q" name="q" type="search" size="42" value="' . htmlspecialchars($term, ENT_QUOTES, 'UTF-8') . '" placeholder="Onderwerp, naam, e-mailadres of tekst"> '
      . '<select name="mailbox" aria-label="Mailbox">' . $mailboxOptions . '</select> '
      . '<select name="state" aria-label="Map">' . $stateOptions . '</select> '
      . '<button type="submit">Zoeken</button>'
      . '</form>';
  }

  /** @param int[] $visibleMailboxIds
   *  @return array<int, array<string, mixed>>
   */
  private function searchRows(array $visibleMailboxIds, string $term, int $mailboxId, string $state): array {
    $query = $this->database->select('brebo_mailbox_message', 'bm');
    $query->join('brebo_mailbox', 'mb', 'mb.id = bm.mailbox_id');
    $query->join('node_field_data', 'n', 'n.nid = bm.communication_id AND n.default_langcode = 1');
    $query->leftJoin('node__field_brebo_mail_from', 'mf', 'mf.entity_id = n.nid AND mf.deleted = 0');
    $query->leftJoin('node__field_brebo_mail_to', 'mt', 'mt.entity_id = n.nid AND mt.deleted = 0');
    $query->leftJoin('node__field_brebo_comm_subject', 'ms', 'ms.entity_id = n.nid AND ms.deleted = 0');
    $query->leftJoin('node__field_brebo_transcript', 'tr', 'tr.entity_id = n.nid AND tr.deleted = 0');
    $query->leftJoin('node__field_brebo_comm_datetime', 'md', 'md.entity_id = n.nid AND md.deleted = 0');

    $query->fields('bm', ['mailbox_id', 'communication_id', 'mail_state']);
    $query->addField('mb', 'label', 'mailbox_label');
    $query->addField('mf', 'field_brebo_mail_from_value', 'mail_from');
    $query->addField('ms', 'field_brebo_comm_subject_value', 'subject');
    $query->addField('md', 'field_brebo_comm_datetime_value', 'mail_datetime');
    $query->condition('n.type', 'brebo_communication');
    $query->condition('bm.mailbox_id', $mailboxId > 0 ? [$mailboxId] : $visibleMailboxIds, 'IN');
    if ($state !== '') {
      $query->condition('bm.mail_state', $state);
    }

    $needle = '%' . $this->database->escapeLike($term) . '%';
    $or = $query->orConditionGroup()
      ->condition('ms.field_brebo_comm_subject_value', $needle, 'LIKE')
      ->condition('mf.field_brebo_mail_from_value', $needle, 'LIKE')
      ->condition('mt.field_brebo_mail_to_value', $needle, 'LIKE')
      ->condition('tr.field_brebo_transcript_value', $needle, 'LIKE');
    $query->condition($or);
    $query->orderBy('md.field_brebo_comm_datetime_value', 'DESC');
    $query->orderBy('bm.changed', 'DESC');
    $query->range(0, 100);

    return array_values(array_map('get_object_vars', $query->execute()->fetchAll()));
  }

}
