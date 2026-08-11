<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

/**
 * Builds a bounded AI drafting contract from a controlled BREBO mail template.
 */
final class AiMailDraftPolicy {

  public function __construct(
    private readonly MailTemplateCatalog $templates,
  ) {}

  /**
   * @param array<string, scalar|null> $context
   *
   * @return array<string, mixed>
   */
  public function build(string $templateId, array $context): array {
    $template = $this->templates->get($templateId);

    return [
      'mode' => 'concept_only',
      'template_id' => $templateId,
      'audience' => $template['audience'],
      'subject_hint' => $template['subject_hint'],
      'required_blocks' => $template['required_blocks'],
      'protected_rules' => $template['protected_rules'],
      'protected_footer' => $template['protected_footer'],
      'context' => $context,
      'output_contract' => [
        'subject' => 'string',
        'body' => 'string',
        'missing_information' => 'string[]',
        'assumptions' => 'string[]',
        'requires_human_review' => TRUE,
      ],
    ];
  }

}
