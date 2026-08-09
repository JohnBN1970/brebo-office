<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\Datetime\TimeInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\node\NodeInterface;
use GuzzleHttp\ClientInterface;

/** Converts a communication source into a reviewable BREBO AI concept. */
final class CommunicationAiProcessor {

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly FileSystemInterface $fileSystem,
    private readonly TimeInterface $time,
  ) {}

  public function isConfigured(): bool {
    return trim((string) getenv('BREBO_OPENAI_API_KEY')) !== '';
  }

  /** Processes one communication; formal establishment remains a human action. */
  public function process(NodeInterface $communication): void {
    if ($communication->bundle() !== 'brebo_communication') {
      throw new \InvalidArgumentException('Alleen BREBO-communicatie kan worden verwerkt.');
    }
    if (!$this->isConfigured()) {
      throw new \RuntimeException('AI-verwerking is niet geconfigureerd: BREBO_OPENAI_API_KEY ontbreekt.');
    }

    $communication->set('field_brebo_ai_status', 'Bezig');
    $communication->setNewRevision(TRUE);
    $communication->setRevisionLogMessage('AI-verwerking gestart.');
    $communication->save();

    try {
      $transcript = trim((string) ($communication->get('field_brebo_transcript')->value ?? ''));
      if ($transcript === '') {
        $transcript = $this->transcribeFirstSource($communication);
      }
      if ($transcript === '') {
        throw new \RuntimeException('Geen transcriptie of bruikbaar bronbestand aanwezig.');
      }

      $result = $this->extract($transcript);
      $communication->set('field_brebo_transcript', $transcript);
      $communication->set('field_brebo_ai_summary', (string) $result['summary']);
      $communication->set('field_brebo_ai_decisions', $this->bullets($result['decisions']));
      $communication->set('field_brebo_ai_actions', $this->actions($result['actions']));
      $communication->set('field_brebo_ai_risks', $this->bullets($result['risks']));
      $communication->set('field_brebo_ai_confidence', (float) $result['confidence']);
      $communication->set('field_brebo_ai_status', 'Controle vereist');
      $communication->set('field_brebo_formal_status', 'AI-concept');
      $communication->set('field_brebo_processed_at', gmdate('Y-m-d\TH:i:s', $this->time->getRequestTime()));
      $communication->set('field_brebo_extract_version', 'brebo-communication-v1');
      $communication->set('field_brebo_process_log', 'Automatisch verwerkt. Transcriptie en extractie vereisen menselijke controle.');
      $communication->setNewRevision(TRUE);
      $communication->setRevisionLogMessage('AI-concept aangemaakt; menselijke controle vereist.');
      $communication->save();
    }
    catch (\Throwable $exception) {
      $communication->set('field_brebo_ai_status', 'Fout');
      $communication->set('field_brebo_process_log', 'AI-verwerking afgebroken: ' . $exception->getMessage());
      $communication->setNewRevision(TRUE);
      $communication->setRevisionLogMessage('AI-verwerking afgebroken zonder formele vaststelling.');
      $communication->save();
      throw $exception;
    }
  }

  private function transcribeFirstSource(NodeInterface $communication): string {
    $files = $communication->get('field_brebo_source_files')->referencedEntities();
    if (!$files) {
      throw new \RuntimeException('Geen opnamebestand gekoppeld.');
    }
    $path = $this->fileSystem->realpath($files[0]->getFileUri());
    if (!$path || !is_readable($path)) {
      throw new \RuntimeException('Het opnamebestand is niet leesbaar.');
    }
    if (filesize($path) > 25 * 1024 * 1024) {
      throw new \RuntimeException('Het opnamebestand is groter dan 25 MB.');
    }
    $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/audio/transcriptions', [
      'headers' => ['Authorization' => 'Bearer ' . getenv('BREBO_OPENAI_API_KEY')],
      'multipart' => [
        ['name' => 'model', 'contents' => getenv('BREBO_OPENAI_TRANSCRIBE_MODEL') ?: 'gpt-transcribe'],
        ['name' => 'language', 'contents' => 'nl'],
        ['name' => 'prompt', 'contents' => 'BREBO, bouw, renovatie, opdrachtgever, uitvoerder, werkvoorbereider, afwijking, scope, stelpost, verrekenpost.'],
        ['name' => 'file', 'contents' => fopen($path, 'rb'), 'filename' => $files[0]->getFilename()],
      ],
      'timeout' => 180,
    ]);
    $payload = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
    return trim((string) ($payload['text'] ?? ''));
  }

  private function extract(string $transcript): array {
    $schema = [
      'type' => 'object',
      'additionalProperties' => FALSE,
      'properties' => [
        'summary' => ['type' => 'string'],
        'decisions' => ['type' => 'array', 'items' => ['type' => 'string']],
        'actions' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => FALSE, 'properties' => [
          'action' => ['type' => 'string'], 'owner' => ['type' => 'string'], 'deadline' => ['type' => 'string'],
        ], 'required' => ['action', 'owner', 'deadline']]],
        'risks' => ['type' => 'array', 'items' => ['type' => 'string']],
        'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
      ],
      'required' => ['summary', 'decisions', 'actions', 'risks', 'confidence'],
    ];
    $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/responses', [
      'headers' => ['Authorization' => 'Bearer ' . getenv('BREBO_OPENAI_API_KEY'), 'Content-Type' => 'application/json'],
      'json' => [
        'model' => getenv('BREBO_OPENAI_TEXT_MODEL') ?: 'gpt-5-mini',
        'instructions' => 'Verwerk uitsluitend expliciet aanwezige feiten. Vul niets aan. Schrijf zakelijk Nederlands. Benoem onzekerheid. Een menselijke BREBO-medewerker stelt formeel vast.',
        'input' => "Maak een controleerbaar BREBO-concept van deze transcriptie:\n\n" . $transcript,
        'text' => ['format' => ['type' => 'json_schema', 'name' => 'brebo_communication_extract', 'strict' => TRUE, 'schema' => $schema]],
      ],
      'timeout' => 180,
    ]);
    $payload = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
    $text = (string) ($payload['output'][0]['content'][0]['text'] ?? '');
    if ($text === '') {
      throw new \RuntimeException('De extractieservice gaf geen bruikbaar resultaat terug.');
    }
    return json_decode($text, TRUE, 512, JSON_THROW_ON_ERROR);
  }

  private function bullets(array $items): string {
    return implode("\n", array_map(static fn ($item): string => '• ' . trim((string) $item), $items));
  }

  private function actions(array $items): string {
    return implode("\n", array_map(static fn (array $item): string => sprintf('• %s — eigenaar: %s — termijn: %s', $item['action'], $item['owner'] ?: 'onbekend', $item['deadline'] ?: 'onbekend'), $items));
  }

}
