<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** Render only; the caller must provide a verified Office operator and CSRF token. */
final class OfficeInzetPersonnelNumberForm {
  public static function render(int $projectId, int $personId, string $displayName, string $number, string $csrfToken, string $basePath): string {
    if ($projectId < 1 || $personId < 1 || !in_array($basePath, ['/office-test'], true)
      || preg_match('/^[a-f0-9]{64}$/D', $csrfToken) !== 1) {
      throw new \InvalidArgumentException('Verified profile form context required');
    }
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<form method="post" action="' . $escape($basePath . '/inzet/medewerker/personeelsnummer') . '">'
      . '<h3>Personeelsnummer wijzigen</h3>'
      . '<p>' . $escape($displayName) . '</p>'
      . '<input type="hidden" name="csrf_token" value="' . $escape($csrfToken) . '">'
      . '<input type="hidden" name="project_id" value="' . $projectId . '">'
      . '<input type="hidden" name="person_id" value="' . $personId . '">'
      . '<label for="personnel-number-' . $personId . '">Personeelsnummer</label>'
      . '<input id="personnel-number-' . $personId . '" name="personnel_number" value="' . $escape($number) . '" maxlength="40" pattern="[A-Za-z0-9._-]+" required autocomplete="off">'
      . '<button type="submit">Opslaan</button></form>';
  }
}
