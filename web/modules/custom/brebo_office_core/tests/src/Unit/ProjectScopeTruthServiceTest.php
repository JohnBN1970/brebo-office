<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_office_core\Unit;

use Drupal\brebo_office_core\Contract\ProjectScopeDecisionRepositoryInterface;
use Drupal\brebo_office_core\Domain\ProjectScopeDecision;
use Drupal\brebo_office_core\Domain\ProjectScopeDisposition;
use Drupal\brebo_office_core\Domain\ProjectScopeStatementType;
use Drupal\brebo_office_core\Service\ProjectScopeTruthService;
use PHPUnit\Framework\TestCase;

final class ProjectScopeTruthServiceTest extends TestCase {

  public function testWakkerstraatLaterMailSupersedesAmbiguousRequestWithoutDeletingHistory(): void {
    $repository = new InMemoryProjectScopeDecisionRepository();
    $service = new ProjectScopeTruthService($repository);

    $requestId = $service->confirm(new ProjectScopeDecision(
      29,
      'rear_ground_floor_pui.replacement',
      ProjectScopeStatementType::REQUESTED,
      ProjectScopeDisposition::OUT_OF_SCOPE,
      'Aanvraag bevat een dubbelzinnig aandachtspunt over vervanging achterzijde BG.',
      'email',
      'WAK29-18904-2@2026-03-30T13:01',
      NULL,
      'achterzijde bg wordt vervangen. Dus is alleen schilderen niet vervangen.',
      'AG-BG-PUI',
      NULL,
      1,
      1774872060,
    ));

    $decisionId = $service->confirm(new ProjectScopeDecision(
      29,
      'rear_ground_floor_pui.replacement',
      ProjectScopeStatementType::DECIDED,
      ProjectScopeDisposition::THIRD_PARTY,
      'De pui achterzijde BG wordt door derden vervangen; BREBO vervangt deze niet.',
      'email',
      'WAK29-18904-2@2026-03-30T13:36',
      NULL,
      'Zij vervangen die pui al in de verbouwing.',
      'AG-BG-PUI',
      NULL,
      1,
      1774874160,
      $requestId,
    ));

    self::assertSame($decisionId, $service->currentTruth(29)[0]['id']);
    self::assertSame('third_party', $service->currentTruth(29)[0]['disposition']);
    self::assertCount(2, $service->history(29, 'rear_ground_floor_pui.replacement'));
    self::assertSame('superseded', $service->history(29, 'rear_ground_floor_pui.replacement')[1]['status']);
  }

  public function testCannotSupersedeDifferentSubject(): void {
    $repository = new InMemoryProjectScopeDecisionRepository();
    $service = new ProjectScopeTruthService($repository);
    $id = $service->confirm($this->decision('roof.renovation', ProjectScopeDisposition::DECLINED));

    $this->expectException(\InvalidArgumentException::class);
    $service->confirm(new ProjectScopeDecision(
      29, 'front.windows.replacement', ProjectScopeStatementType::DECIDED,
      ProjectScopeDisposition::ALTERNATIVE, 'Kunststof als alternatief.', 'email', 'follow-up',
      NULL, NULL, 'VG', NULL, 1, 1774875000, $id,
    ));
  }

  private function decision(string $subject, ProjectScopeDisposition $disposition): ProjectScopeDecision {
    return new ProjectScopeDecision(
      29, $subject, ProjectScopeStatementType::DECIDED, $disposition,
      'Bevestigd projectbesluit.', 'brebo_decision', 'test', NULL, NULL, NULL, NULL, 1, 1774875000,
    );
  }

}

final class InMemoryProjectScopeDecisionRepository implements ProjectScopeDecisionRepositoryInterface {

  /** @var array<int,array<string,mixed>> */
  private array $rows = [];
  private int $nextId = 1;

  public function get(int $decisionId): ?array {
    return $this->rows[$decisionId] ?? NULL;
  }

  public function currentForProject(int $projectId): array {
    return array_values(array_filter($this->rows, static fn(array $row): bool =>
      $row['project_id'] === $projectId && $row['status'] === 'active'
    ));
  }

  public function historyForSubject(int $projectId, string $subjectKey): array {
    $rows = array_values(array_filter($this->rows, static fn(array $row): bool =>
      $row['project_id'] === $projectId && $row['subject_key'] === $subjectKey
    ));
    usort($rows, static fn(array $a, array $b): int => $b['id'] <=> $a['id']);
    return $rows;
  }

  public function save(ProjectScopeDecision $decision): int {
    $id = $this->nextId++;
    $this->rows[$id] = [
      'id' => $id,
      'project_id' => $decision->projectId,
      'subject_key' => $decision->subjectKey,
      'statement_type' => $decision->statementType->value,
      'disposition' => $decision->disposition->value,
      'status' => 'active',
    ];
    return $id;
  }

  public function markSuperseded(int $decisionId, int $supersededById): void {
    $this->rows[$decisionId]['status'] = 'superseded';
    $this->rows[$decisionId]['superseded_by_id'] = $supersededById;
  }

  public function transactional(callable $callback): mixed {
    return $callback();
  }

}
