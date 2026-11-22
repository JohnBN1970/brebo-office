<?php
declare(strict_types=1);
namespace Brebo\Office\Process;

/**
 * FR-201 v1.0, BREBO Proceshandboek, 7 augustus 2026.
 * Source-defined intake fields. This is a metadata definition only:
 * CRM intake IDs and relation references must be resolved by Office, not fabricated.
 */
final class OfficeProcessFormFr201
{
    public const VERSION='1.0';
    public const FIELDS=[
        'intake_id'=>['Intake-ID','INT-YYYY-NNNN; uniek centraal volgnummer.',true],
        'ontvangst'=>['Ontvangst','Datum/tijd, kanaal, ontvanger en bronverwijzing.',true],
        'klant_rel_id'=>['Klant / REL-ID','Organisatie, juridische contractpartij en relatiecode.',true],
        'contactpersoon'=>['Contactpersoon','Naam, functie, bevoegdheid, telefoon en e-mail.',true],
        'project_locatie'=>['Project / locatie','Projectnaam, adres, object, complex en relevante bouwdelen.',true],
        'klantvraag'=>['Klantvraag','Vraag en gewenst resultaat in woorden van de klant.',true],
        'werksoorten_scope'=>['Werksoorten / scope','Gevraagde werkzaamheden, hoeveelheden en zichtbare grens.',true],
        'aanleiding'=>['Aanleiding','Onderhoud, gebrek, verduurzaming, schade, advies, aanbesteding of anders.',true],
        'planning_termijnen'=>['Planning / termijnen','Gewenste start, oplevering, offertedatum en fatale termijnen.',true],
        'budget_prijsbasis'=>['Budget / prijsbasis','Bekend budget, ramingsniveau, contractvorm en btw-context.',false],
        'beschikbare_stukken'=>['Beschikbare stukken','Bestek, tekeningen, MJOP, foto’s, rapporten, eisen en eerdere offertes.',false],
        'opnamebehoefte'=>['Opnamebehoefte','Fysiek/digitaal, bereikbaarheid, maatvoering en benodigde deskundigheid.',true],
        'risicos_omgeving'=>['Risico’s / omgeving','Veiligheid, bewoners, vergunning, logistiek, schade en onzekerheden.',true],
        'selectie_concurrentie'=>['Selectie / concurrentie','Procedure, beoordelingscriteria, concurrentie en gunningsroute.',false],
        'commerciele_fit'=>['Commerciële fit','Strategische passendheid, capaciteit, expertise, kans en randvoorwaarden.',true],
        'eigenaar_vervolg'=>['Eigenaar / vervolg','Verantwoordelijke, actie, termijn en eerstvolgende contactmoment.',true],
        'besluit_overdracht'=>['Besluit / overdracht','Doorgaan, aanvullen, parkeren of afwijzen plus bewijs en koppelingen.',true],
    ];

    public static function missingRequired(array $data): array
    {
        $missing=[];
        foreach (self::FIELDS as $key=>$definition) {
            if (!$definition[2]) continue;
            $value=$data[$key]??null;
            if (!is_scalar($value) || trim((string)$value)==='') $missing[]=$key;
        }
        return $missing;
    }
}
