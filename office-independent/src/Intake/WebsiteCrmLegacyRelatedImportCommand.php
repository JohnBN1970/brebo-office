<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/** Offline, fail-closed import for protected Drupal CRM related exports. */
final class WebsiteCrmLegacyRelatedImportCommand {

  public static function run(array $argv): int {
    if (count($argv) !== 3 || !in_array($argv[1], ['brebo_contact', 'brebo_organization', 'brebo_opportunity_event'], true)
      || !is_file($argv[2]) || is_link($argv[2])) {
      fwrite(STDERR, "Usage: php import-legacy-crm-related.php <brebo_contact|brebo_organization|brebo_opportunity_event> protected-export.json\n");
      return 2;
    }
    try {
      $size = filesize($argv[2]);
      if ($size === false || $size > 20_000_000) {
        throw new \RuntimeException('CRM related export exceeds 20 MB.');
      }
      $raw = file_get_contents($argv[2]);
      if ($raw === false || strlen($raw) > 20_000_000) {
        throw new \RuntimeException('Could not read protected CRM related export.');
      }
      $rows = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
      if (!is_array($rows) || !array_is_list($rows)) {
        throw new \InvalidArgumentException('Expected JSON list.');
      }
      $normalized = [];
      $seen = [];
      foreach ($rows as $row) {
        if (!is_array($row) || ($row['bundle'] ?? null) !== $argv[1]
          || !is_int($row['legacy_node_id'] ?? null) || $row['legacy_node_id'] < 1
          || !is_array($row['fields'] ?? null)) {
          throw new \InvalidArgumentException('Invalid or mixed CRM export row.');
        }
        $id = $row['legacy_node_id'];
        if (isset($seen[$id])) {
          throw new \InvalidArgumentException('Duplicate CRM source ID.');
        }
        $seen[$id] = true;
        if ($argv[1] === 'brebo_opportunity_event') {
          $fields = $row['fields'];
          $reference = $fields['field_brebo_event_opp_ref'][0]['target_id'] ?? null;
          $from = $fields['field_brebo_event_from_stage'][0]['value'] ?? '';
          $to = $fields['field_brebo_event_to_stage'][0]['value'] ?? null;
          $occurred = $fields['field_brebo_event_datetime'][0]['value'] ?? null;
          if (is_string($reference) && ctype_digit($reference)) {
            $reference = (int) $reference;
          }
          if (!is_int($reference) || $reference < 1 || !is_string($from)
            || !is_string($to) || !is_string($occurred)) {
            throw new \InvalidArgumentException('Unmapped CRM stage event fields.');
          }
          $normalized[] = $row + [
            'legacy_event_node_id' => $id,
            'legacy_opportunity_node_id' => $reference,
            'from_stage' => $from,
            'to_stage' => $to,
            'occurred_at' => $occurred,
          ];
        }
        else {
          $normalized[] = $row + ['party_type' => $argv[1]];
        }
      }
      fwrite(STDOUT, 'Validated ' . count($normalized) . ' ' . $argv[1] . " records.\n");
      if (getenv('OFFICE_CRM_IMPORT_CONFIRM') !== 'YES') {
        fwrite(STDOUT, "Dry run only. Set OFFICE_CRM_IMPORT_CONFIRM=YES for independent staging import.\n");
        return 0;
      }
      $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
      if (!is_string($dsn) || $dsn === '') {
        throw new \RuntimeException('Independent database DSN missing.');
      }
      $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
      $store = $argv[1] === 'brebo_opportunity_event'
        ? new WebsiteCrmLegacyStageEventStore($db)
        : new WebsiteCrmLegacyPartyStore($db);
      $db->exec($argv[1] === 'brebo_opportunity_event'
        ? WebsiteCrmLegacyStageEventStore::schema()
        : WebsiteCrmLegacyPartyStore::schema());
      $inserted = $store->import($normalized);
      fwrite(STDOUT, "Imported {$inserted} new staging records.\n");
      return 0;
    }
    catch (\Throwable $e) {
      fwrite(STDERR, 'CRM related import rejected: ' . $e->getMessage() . "\n");
      return 1;
    }
  }
}
