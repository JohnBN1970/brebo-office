<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Source;

/**
 * Normalizes one external mail source into the canonical intake payload.
 *
 * Source adapters may read Gmail, Microsoft 365, IMAP exports or migration
 * batches, but they never write Drupal dossier objects themselves.
 */
interface MailSourceAdapterInterface {

  /**
   * Returns normalized messages ready for the Mail Intake pipeline.
   *
   * Every item MUST contain source_id, subject and body. Source adapters SHOULD
   * preserve source_hash, received_at, from, to, thread_id and attachment
   * references whenever the source exposes them.
   *
   * @return iterable<array<string, mixed>>
   */
  public function messages(): iterable;

}
