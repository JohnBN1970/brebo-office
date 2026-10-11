<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Framework-neutral controller. An Office session adapter must be wired in
 * before this controller can be mounted in any HTTP front controller.
 */
final class WebsiteCrmDraftHttpController {

  public function __construct(
    private readonly WebsiteCrmDraftAccessGate $access,
    private readonly WebsiteCrmDraftApplicationService $application,
    private readonly WebsiteCrmDraftFormRenderer $renderer,
    private readonly WebsiteCrmLegacyReadRepository $legacy,
    private readonly WebsiteCrmOpportunityDraftStore $drafts,
  ) {}

  /** @return array{status:int,body:string} */
  public function form(int $opportunityId, int $now): array {
    try {
      $operator = $this->access->requireEditor();
    }
    catch (\DomainException) {
      return ['status' => 403, 'body' => 'Geen toegang tot CRM bewerken.'];
    }
    if ($opportunityId < 1) {
      return ['status' => 400, 'body' => 'Ongeldig kansnummer.'];
    }
    $source = $this->legacy->opportunity($opportunityId);
    if ($source === null) {
      return ['status' => 404, 'body' => 'Verkoopkans niet gevonden.'];
    }
    return [
      'status' => 200,
      'body' => $this->renderer->render($source, $this->drafts->get($opportunityId), $this->application->formToken($operator, $opportunityId, $now)),
    ];
  }

  /** @param array<string,mixed> $post
   *  @return array{status:int,body:string}
   */
  public function submit(array $post, int $now): array {
    try {
      $operator = $this->access->requireEditor();
    }
    catch (\DomainException) {
      return ['status' => 403, 'body' => 'Geen toegang tot CRM bewerken.'];
    }
    try {
      $version = $this->application->save($operator, $post, $now);
      return ['status' => 200, 'body' => 'CRM-concept opgeslagen (versie ' . $version . ').'];
    }
    catch (\InvalidArgumentException) {
      return ['status' => 400, 'body' => 'Ongeldige CRM-invoer.'];
    }
    catch (\DomainException) {
      return ['status' => 403, 'body' => 'Ongeldige of verlopen bewerkbeveiliging.'];
    }
    catch (\RuntimeException $e) {
      return ['status' => str_contains($e->getMessage(), 'concurrently') ? 409 : 404, 'body' => 'CRM-concept niet opgeslagen; controleer de huidige versie en bron.'];
    }
  }
}
