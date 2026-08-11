<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

/**
 * Controlled template definitions for BREBO outbound communication.
 *
 * AI may draft variable content inside these structures, but protected company
 * blocks are owned by BREBO Office and must not be silently rewritten by AI.
 */
final class MailTemplateCatalog {

  /** @return array<string, array<string, mixed>> */
  public function all(): array {
    return [
      'bewoner_start_werkzaamheden' => $this->template(
        'Bewoner - start werkzaamheden',
        'bewoner',
        'Start werkzaamheden',
        ['planning', 'werkzaamheden', 'verwachte overlast', 'bereikbaarheid', 'contact'],
      ),
      'bewoner_planning_wijziging' => $this->template(
        'Bewoner - planningwijziging',
        'bewoner',
        'Wijziging planning',
        ['wat wijzigt', 'waarom', 'nieuwe planning', 'gevolgen', 'contact'],
      ),
      'bewoner_afspraak' => $this->template(
        'Bewoner - afspraakverzoek',
        'bewoner',
        'Afspraak werkzaamheden',
        ['reden afspraak', 'voorgestelde momenten', 'voorbereiding bewoner', 'contact'],
      ),
      'klacht_reactie' => $this->template(
        'Reactie op klacht',
        'bewoner_opdrachtgever',
        'Reactie op uw melding',
        ['erkenning melding', 'feitelijke stand van zaken', 'actie', 'termijn', 'contact'],
      ),
      'oplevering' => $this->template(
        'Oplevering',
        'bewoner_opdrachtgever',
        'Oplevering werkzaamheden',
        ['uitgevoerde werkzaamheden', 'oplevermoment', 'open punten', 'garantie/nazorg', 'contact'],
      ),
      'garantie_nazorg' => $this->template(
        'Garantie en nazorg',
        'bewoner_opdrachtgever',
        'Garantie / nazorg',
        ['melding', 'beoordeling', 'vervolgactie', 'planning', 'contact'],
      ),
      'leverancier_offerte' => $this->template(
        'Leverancier - offerte / inkoop',
        'leverancier',
        'Aanvraag / afstemming',
        ['scope', 'hoeveelheden', 'planning', 'voorwaarden', 'reactiedatum'],
      ),
      'leverancier_planning' => $this->template(
        'Leverancier - planning',
        'leverancier',
        'Afstemming planning',
        ['project', 'werkpakket', 'datum/tijd', 'logistiek', 'bevestiging gevraagd'],
      ),
      'organisatie_algemeen' => $this->template(
        'Organisatiebericht',
        'organisatie',
        'Bericht van BREBO',
        ['aanleiding', 'kernboodschap', 'gevolgen', 'eventuele actie ontvanger', 'contact'],
      ),
    ];
  }

  /** @return array<string, mixed> */
  public function get(string $id): array {
    $templates = $this->all();
    if (!isset($templates[$id])) {
      throw new \InvalidArgumentException(sprintf('Onbekend BREBO-mailsjabloon: %s', $id));
    }
    return $templates[$id];
  }

  /** @return array<string, mixed> */
  private function template(string $label, string $audience, string $subjectHint, array $requiredBlocks): array {
    return [
      'label' => $label,
      'audience' => $audience,
      'subject_hint' => $subjectHint,
      'required_blocks' => $requiredBlocks,
      'protected_rules' => [
        'Geen onbewezen feiten toevoegen.',
        'Geen financiële, contractuele, technische of planningstoezegging doen zonder bron of mandaat.',
        'Geen juridische of garantievoorwaarden vrij herschrijven.',
        'Onzekerheid zichtbaar maken als controlepunt.',
        'Geen verzending zonder expliciete menselijke vrijgave.',
      ],
      'protected_footer' => "Met vriendelijke groet,\nBREBO\ninfo@brebobv.nl",
    ];
  }

}
