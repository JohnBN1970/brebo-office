<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

/**
 * Produces a non-authoritative module routing suggestion for incoming mail.
 *
 * This does not create or confirm any dossier link or change a workflow state.
 */
final class MailModuleRoutingAdvisor {

  /**
   * @param array<int|string, mixed> $signals
   * @param array<string, mixed> $canonicalContext
   * @param array<string, mixed> $crmContext
   *
   * @return array{module:string,state:string,basis:string}
   */
  public function advise(string $classification, float $confidence, array $signals, array $canonicalContext, array $crmContext): array {
    $routes = [
      'financieel' => 'finance',
      'offerte' => 'crm',
      'planning' => 'projecten',
      'oplevering' => 'projecten',
      'meerwerk' => 'projecten',
      'inkoop' => 'projecten',
      'leverancier' => 'projecten',
      'garantie' => 'projecten',
      'klacht' => 'projecten',
      'bewonersmelding' => 'projecten',
      'personeel' => 'personeel',
    ];

    $module = $routes[$classification] ?? 'email';
    if ($module === 'email' || $confidence < 85.0) {
      return [
        'module' => $module,
        'state' => 'review',
        'basis' => 'Geen eenduidige module of onvoldoende classificatiezekerheid.',
      ];
    }

    // Classification alone is not enough to select a project or customer.
    // Keep every suggestion reviewable until an authoritative match exists.
    $hasProject = (int) ($canonicalContext['project_id'] ?? 0) > 0;
    $hasCrm = (int) ($crmContext['organization_id'] ?? 0) > 0
      || (int) ($crmContext['contact_id'] ?? 0) > 0;
    $matched = match ($module) {
      'projecten' => $hasProject,
      'crm' => $hasCrm,
      default => FALSE,
    };

    return [
      'module' => $module,
      'state' => $matched ? 'suggested' : 'review',
      'basis' => $matched
        ? 'Sterke classificatie en bestaande contextmatch; dossierkoppeling blijft een voorstel.'
        : 'Module herkend; dossiercontext of expliciete autorisatie moet nog worden gecontroleerd.',
    ];
  }

}
