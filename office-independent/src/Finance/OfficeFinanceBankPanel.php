<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/** Safe read-only bank panel: no inferred balances or confirmed payments. */
final class OfficeFinanceBankPanel {
  private static function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
  public static function render(?array $snapshot): string {
    $html = '<section aria-label="Bankgegevens"><h2>Bankgegevens · Moneybird</h2>';
    if ($snapshot === null) {
      return $html . '<p>Bankgegevens nog niet verbonden. Geen saldo beschikbaar.</p></section>';
    }
    if (($snapshot['provider'] ?? null) !== 'moneybird' || ($snapshot['balances_available'] ?? null) !== false
        || ($snapshot['period'] ?? null) !== 'this_month') {
      throw new \InvalidArgumentException('Unverified bank snapshot');
    }
    $html .= '<p class="muted">Alleen lezen · Saldi niet geverifieerd · Geen automatische betalingsbevestiging.</p>';
    $html .= '<p>Bankmutaties: deze maand volgens Moneybird. Geen volledig historisch overzicht.</p>';
    $html .= '<h3>Bankrekeningen</h3><div class="table"><table><thead><tr><th>Rekening</th><th>Identificatie</th><th>Valuta</th><th>Saldo</th></tr></thead><tbody>';
    foreach (($snapshot['accounts'] ?? []) as $account) {
      $html .= '<tr><td>'.self::e($account['name'] ?? '').'</td><td>'.self::e($account['identifier'] ?? '').'</td><td>'.self::e($account['currency'] ?? '').'</td><td>Niet beschikbaar</td></tr>';
    }
    $html .= '</tbody></table></div><h3>Bankmutaties</h3><div class="table"><table><thead><tr><th>Datum</th><th>Omschrijving</th><th>Bedrag</th><th>Controle</th></tr></thead><tbody>';
    foreach (array_slice($snapshot['transactions'] ?? [], 0, 100) as $transaction) {
      $html .= '<tr><td>'.self::e($transaction['booking_date'] ?? '').'</td><td>'.self::e($transaction['description'] ?? '').'</td><td>'.self::e($transaction['amount'] ?? '').' '.self::e($transaction['currency'] ?? '').'</td><td>Niet geverifieerd</td></tr>';
    }
    return $html . '</tbody></table></div></section>';
  }
}

