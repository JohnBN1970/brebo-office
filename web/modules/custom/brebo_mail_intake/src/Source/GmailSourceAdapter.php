<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Source;

use Drupal\Core\Http\ClientFactory;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;

/**
 * Read-only Gmail source adapter using OAuth2 offline access.
 */
final class GmailSourceAdapter implements MailSourceAdapterInterface {

  private const BACKFILL_TOKEN_STATE = 'brebo_mail_intake.gmail_backfill_page_token';
  private const BACKFILL_COMPLETE_STATE = 'brebo_mail_intake.gmail_backfill_complete';

  public function __construct(
    private readonly ClientFactory $httpClientFactory,
    private readonly StateInterface $state,
  ) {}

  public function isConfigured(): bool {
    foreach (['BREBO_GMAIL_CLIENT_ID', 'BREBO_GMAIL_CLIENT_SECRET', 'BREBO_GMAIL_REFRESH_TOKEN', 'BREBO_MAIL_INTAKE_UID'] as $name) {
      if (trim((string) getenv($name)) === '') {
        return FALSE;
      }
    }
    return (int) getenv('BREBO_MAIL_INTAKE_UID') > 0;
  }

  /** {@inheritdoc} */
  public function messages(): iterable {
    if (!$this->isConfigured()) {
      return;
    }

    $client = $this->gmailClient();
    $now = time();
    $lastPoll = (int) $this->state->get('brebo_mail_intake.gmail_last_poll_epoch', 0);
    if ($lastPoll <= 0) {
      $lookback = max(300, min(604800, (int) (getenv('BREBO_GMAIL_INITIAL_LOOKBACK_SECONDS') ?: 3600)));
      $lastPoll = $now - $lookback;
    }

    $list = $client->get('https://gmail.googleapis.com/gmail/v1/users/me/messages', [
      'query' => [
        'maxResults' => 50,
        'q' => 'after:' . max(0, $lastPoll - 300),
      ],
    ]);
    $payload = json_decode((string) $list->getBody(), TRUE, flags: JSON_THROW_ON_ERROR);
    $maxEpoch = $lastPoll;

    foreach ($payload['messages'] ?? [] as $item) {
      $id = trim((string) ($item['id'] ?? ''));
      if ($id === '') {
        continue;
      }

      $normalized = $this->normalize($this->messageById($client, $id));
      $maxEpoch = max($maxEpoch, (int) ($normalized['_internal_epoch'] ?? 0));
      unset($normalized['_internal_epoch']);
      yield $normalized;
    }

    $this->state->set('brebo_mail_intake.gmail_last_poll_epoch', max($maxEpoch, $now));
  }

  /**
   * Returns one historical Gmail page, newest first, with an independent cursor.
   *
   * The Gmail page token is persisted only after the complete page has been
   * normalized. Queue/ingestor duplicate protection makes retries harmless.
   *
   * @return iterable<array<string, mixed>>
   */
  public function backfillMessages(): iterable {
    if (!$this->isConfigured() || $this->isBackfillComplete()) {
      return;
    }

    $client = $this->gmailClient();
    $batchSize = max(1, min(100, (int) (getenv('BREBO_GMAIL_BACKFILL_BATCH_SIZE') ?: 25)));
    $query = ['maxResults' => $batchSize];
    $pageToken = trim((string) $this->state->get(self::BACKFILL_TOKEN_STATE, ''));
    if ($pageToken !== '') {
      $query['pageToken'] = $pageToken;
    }

    $list = $client->get('https://gmail.googleapis.com/gmail/v1/users/me/messages', ['query' => $query]);
    $payload = json_decode((string) $list->getBody(), TRUE, flags: JSON_THROW_ON_ERROR);
    $normalizedMessages = [];

    foreach ($payload['messages'] ?? [] as $item) {
      $id = trim((string) ($item['id'] ?? ''));
      if ($id === '') {
        continue;
      }
      $normalizedMessages[] = $this->normalize($this->messageById($client, $id));
    }

    usort($normalizedMessages, static fn(array $a, array $b): int => ((int) ($b['_internal_epoch'] ?? 0)) <=> ((int) ($a['_internal_epoch'] ?? 0)));
    foreach ($normalizedMessages as $normalized) {
      unset($normalized['_internal_epoch']);
      yield $normalized;
    }

    $nextPageToken = trim((string) ($payload['nextPageToken'] ?? ''));
    if ($nextPageToken === '') {
      $this->state->delete(self::BACKFILL_TOKEN_STATE);
      $this->state->set(self::BACKFILL_COMPLETE_STATE, TRUE);
    }
    else {
      $this->state->set(self::BACKFILL_TOKEN_STATE, $nextPageToken);
    }
  }

  public function isBackfillComplete(): bool {
    return (bool) $this->state->get(self::BACKFILL_COMPLETE_STATE, FALSE);
  }

  public function resetBackfill(): void {
    $this->state->delete(self::BACKFILL_TOKEN_STATE);
    $this->state->delete(self::BACKFILL_COMPLETE_STATE);
  }

  private function gmailClient(): ClientInterface {
    return $this->httpClientFactory->fromOptions([
      'timeout' => 20,
      'headers' => ['Authorization' => 'Bearer ' . $this->accessToken()],
    ]);
  }

  /** @return array<string, mixed> */
  private function messageById(ClientInterface $client, string $id): array {
    $response = $client->get('https://gmail.googleapis.com/gmail/v1/users/me/messages/' . rawurlencode($id), [
      'query' => ['format' => 'full'],
    ]);
    return json_decode((string) $response->getBody(), TRUE, flags: JSON_THROW_ON_ERROR);
  }

  private function accessToken(): string {
    $client = $this->httpClientFactory->fromOptions(['timeout' => 20]);
    $response = $client->post('https://oauth2.googleapis.com/token', [
      'form_params' => [
        'client_id' => (string) getenv('BREBO_GMAIL_CLIENT_ID'),
        'client_secret' => (string) getenv('BREBO_GMAIL_CLIENT_SECRET'),
        'refresh_token' => (string) getenv('BREBO_GMAIL_REFRESH_TOKEN'),
        'grant_type' => 'refresh_token',
      ],
    ]);
    $payload = json_decode((string) $response->getBody(), TRUE, flags: JSON_THROW_ON_ERROR);
    $token = trim((string) ($payload['access_token'] ?? ''));
    if ($token === '') {
      throw new \RuntimeException('Gmail OAuth refresh leverde geen access token op.');
    }
    return $token;
  }

  /** @param array<string, mixed> $message */
  private function normalize(array $message): array {
    $payload = is_array($message['payload'] ?? NULL) ? $message['payload'] : [];
    $headers = [];
    foreach ($payload['headers'] ?? [] as $header) {
      $name = strtolower(trim((string) ($header['name'] ?? '')));
      if ($name !== '') {
        $headers[$name] = trim((string) ($header['value'] ?? ''));
      }
    }

    $body = trim($this->extractText($payload));
    if ($body === '') {
      $body = trim((string) ($message['snippet'] ?? ''));
    }
    if ($body === '') {
      $body = '[Geen leesbare tekstinhoud; bronbericht blijft herleidbaar via Gmail message-id.]';
    }

    $internalMs = (int) ($message['internalDate'] ?? 0);
    $internalEpoch = $internalMs > 0 ? (int) floor($internalMs / 1000) : time();
    $labelIds = array_map('strval', $message['labelIds'] ?? []);

    return [
      'source_id' => 'gmail:' . (string) ($message['id'] ?? ''),
      'source_hash' => hash('sha256', json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
      'thread_id' => trim((string) ($message['threadId'] ?? '')),
      'subject' => $headers['subject'] ?? '(zonder onderwerp)',
      'body' => $body,
      'from' => $headers['from'] ?? '',
      'to' => implode("\n", array_filter([$headers['to'] ?? '', $headers['cc'] ?? ''])),
      'received_at' => gmdate(DATE_ATOM, $internalEpoch),
      'direction' => in_array('SENT', $labelIds, TRUE) ? 'Uitgaand' : 'Inkomend',
      '_internal_epoch' => $internalEpoch,
    ];
  }

  /** @param array<string, mixed> $part */
  private function extractText(array $part): string {
    $mime = strtolower((string) ($part['mimeType'] ?? ''));
    $data = (string) ($part['body']['data'] ?? '');
    if ($data !== '' && ($mime === 'text/plain' || $mime === 'text/html')) {
      $decoded = $this->base64UrlDecode($data);
      return $mime === 'text/html' ? trim(strip_tags($decoded)) : trim($decoded);
    }

    $plain = '';
    $html = '';
    foreach ($part['parts'] ?? [] as $child) {
      if (!is_array($child)) {
        continue;
      }
      $text = $this->extractText($child);
      if ($text === '') {
        continue;
      }
      if (strtolower((string) ($child['mimeType'] ?? '')) === 'text/plain') {
        $plain .= ($plain === '' ? '' : "\n\n") . $text;
      }
      elseif ($html === '') {
        $html = $text;
      }
    }
    return $plain !== '' ? $plain : $html;
  }

  private function base64UrlDecode(string $value): string {
    $value = strtr($value, '-_', '+/');
    $padding = strlen($value) % 4;
    if ($padding !== 0) {
      $value .= str_repeat('=', 4 - $padding);
    }
    $decoded = base64_decode($value, TRUE);
    return $decoded === FALSE ? '' : $decoded;
  }

}
