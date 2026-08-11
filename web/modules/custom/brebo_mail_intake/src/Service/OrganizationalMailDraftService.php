<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\node\NodeInterface;

/**
 * Creates controlled organization/bulk-style mail drafts one recipient at a time.
 *
 * Recipient selection and consent remain outside this service. This service only
 * turns an already selected recipient plus reviewed content into an auditable
 * BREBO communication concept.
 */
final class OrganizationalMailDraftService {

  public function __construct(
    private readonly MailTemplateCatalog $templates,
    private readonly OutboundMailService $outbound,
  ) {}

  /**
   * @param array{to:string,subject:string,body:string,building_id?:int,project_id?:int,context_id?:int} $draft
   */
  public function create(string $templateId, array $draft): NodeInterface {
    $template = $this->templates->get($templateId);
    $body = trim((string) ($draft['body'] ?? ''));
    if ($body === '') {
      throw new \InvalidArgumentException('Organisatiemail vereist gecontroleerde berichtinhoud.');
    }

    $footer = trim((string) $template['protected_footer']);
    if ($footer !== '' && !str_contains($body, $footer)) {
      $body .= "\n\n" . $footer;
    }

    $draft['body'] = $body;
    $node = $this->outbound->createDraft($draft);
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage(sprintf(
      'Mailconcept opgebouwd vanuit gecontroleerd BREBO-sjabloon %s; menselijke controle en verzendvrijgave blijven verplicht.',
      $templateId,
    ));
    $node->save();
    return $node;
  }

}
