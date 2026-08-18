<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

/**
 * Determines whether a digital day start can be formally released.
 */
final class WorkforceDayStartControl {

  /**
   * @param array<string, mixed> $input
   * @return array{status: string, blocks: array<int, string>, warnings: array<int, string>}
   */
  public function assess(array $input): array {
    $blocks = [];
    $warnings = [];
    foreach ([
      'work' => 'werkzaamheden',
      'access' => 'toegang en logistiek',
      'risks' => 'risico’s en maatregelen',
      'controls' => 'controlepunten',
      'contacts' => 'contact- en escalatiegegevens',
    ] as $key => $label) {
      if (trim((string) ($input[$key] ?? '')) === '') {
        $blocks[] = $label . ' ontbreken';
      }
    }

    if (empty($input['project_matches']) || empty($input['building_matches'])) {
      $blocks[] = 'project of gebouw wijkt af van de gekoppelde dienst';
    }
    if (($input['shift_status'] ?? '') !== 'Gepubliceerd') {
      $blocks[] = 'dienst is nog niet gepubliceerd';
    }
    if (!empty($input['shift_open'])) {
      $blocks[] = 'benodigde personeelsbezetting is nog niet compleet';
    }
    if (($input['qualification_status'] ?? 'Niet gecontroleerd') === 'Blokkade') {
      $blocks[] = 'personeelskwalificatie blokkeert uitvoering';
    }
    elseif (($input['qualification_status'] ?? '') === 'Waarschuwing') {
      $warnings[] = 'personeelskwalificatie heeft een waarschuwing';
    }

    foreach (($input['resource_controls'] ?? []) as $control) {
      if ($control === 'Blokkade') {
        $blocks[] = 'materieelreservering is geblokkeerd';
      }
      elseif ($control === 'Waarschuwing') {
        $warnings[] = 'materieelreservering heeft een waarschuwing';
      }
      elseif ($control !== 'Vrijgegeven') {
        $blocks[] = 'materieelreservering is niet vrijgegeven';
      }
    }

    if (empty($input['has_building_location'])) {
      $warnings[] = 'PDOK-gebouwlocatie ontbreekt voor navigatie en klokcontrole';
    }

    return [
      'status' => $blocks !== [] ? 'Blokkade' : ($warnings !== [] ? 'Waarschuwing' : 'Gereed'),
      'blocks' => array_values(array_unique($blocks)),
      'warnings' => array_values(array_unique($warnings)),
    ];
  }

  /**
   * @return array{status: string, message: string}
   */
  public function assessAcknowledgement(int $currentVersion, int $receivedVersion, bool $understood, string $question): array {
    if ($receivedVersion !== $currentVersion) {
      return ['status' => 'Blokkade', 'message' => 'De bevestiging hoort niet bij de actuele dagstartversie.'];
    }
    if (!$understood) {
      return [
        'status' => trim($question) === '' ? 'Blokkade' : 'Vraag open',
        'message' => trim($question) === '' ? 'Leg vast wat nog onduidelijk is.' : 'Vraag moet vóór uitvoering worden beantwoord.',
      ];
    }
    return ['status' => 'Begrepen', 'message' => 'Actuele dagstartversie is ontvangen en begrepen.'];
  }

}
