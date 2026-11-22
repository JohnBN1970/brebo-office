<?php
declare(strict_types=1);
namespace Brebo\Office\Process;

/**
 * CL-204 v1.0 projectopname: discipline-independent source checklist.
 * A draft may be incomplete; completion checks are not deal-conversion gates.
 * Context IDs reference Office records; no duplicate project/building master data.
 */
final class OfficeCl204Projectopname
{
    public const CODE = 'CL-204';
    public const VERSION = '1.0';
    public const CONTEXT = 'buildings_inspection';

    /** Source checklist; applicability and evidence are stored per item, not hard-coded per trade. */
    public static function controls(): array
    {
        return [
            'opnameopdracht' => ['label'=>'Opnameopdracht', 'detail'=>'Doel, scope, gebouw(delen), vraagstelling, output, deadline en ontvangers'],
            'bronstukken' => ['label'=>'Bronstukken', 'detail'=>'Tekeningen, bestek, MJOP, contract, historie, meldingen, fotos en ontbrekende stukken'],
            'opnamevorm' => ['label'=>'Opnamevorm', 'detail'=>'Fysiek, digitaal of gecombineerd, motivering en verificatiegrens'],
            'identiteit' => ['label'=>'Identiteit', 'detail'=>'Project-ID, adres, object, bouwdeel, locatie, datum, tijd en opnemer'],
            'toegang' => ['label'=>'Toegang', 'detail'=>'Afspraak, sleutels, toestemming, bewonersbericht, begeleider en werktijden'],
            'veiligheid' => ['label'=>'Veiligheid', 'detail'=>'LMRA, afzetting, hoogte, constructie, asbest/gevaarlijke stoffen en stopgrens'],
            'bouwkundige_staat' => ['label'=>'Bouwkundige staat', 'detail'=>'Materiaal, detaillering, aansluitingen, schade, vervorming, vocht en vervuiling'],
            'maatvoering' => ['label'=>'Maatvoering', 'detail'=>'Aantallen, lengtes, oppervlaktes, referentiemaat, meetmethode en tolerantie'],
            'bereikbaarheid' => ['label'=>'Bereikbaarheid', 'detail'=>'Steiger, hoogwerker, hijsen, parkeren, opslag, stroom, water en afval'],
            'omgeving_bewoners' => ['label'=>'Omgeving/bewoners', 'detail'=>'Gebruik, toegankelijkheid, privacy, geluid, hinder en communicatiebeperkingen'],
            'te_behouden_delen' => ['label'=>'Te behouden delen', 'detail'=>'Bescherming, proefvlakken, bestaande voorzieningen en herstelgrenzen'],
            'kwaliteit_garantie' => ['label'=>'Kwaliteit/garantie', 'detail'=>'Keuringspunten, producteisen, ondergrond, garanties en onderhoudsvoorwaarden'],
            'foto_video' => ['label'=>'Foto/video', 'detail'=>'Overzicht, tussenbeeld, detail, oriëntatie, schaal, bron en actualiteit'],
            'risico_urgentie' => ['label'=>'Risico/urgentie', 'detail'=>'Kans, impact, gevolgschade, termijn en tijdelijke beheersing'],
            'gevolg_bij_uitstel' => ['label'=>'Gevolg bij uitstel', 'detail'=>'Waarschijnlijke schade, kostenstijging, hinder, veiligheid en garantie-effect'],
            'open_punten' => ['label'=>'Open punten', 'detail'=>'Onzekerheid, actie, eigenaar, termijn, bewijsbehoefte en blokkade'],
            'vrijgave_overdracht' => ['label'=>'Vrijgave/overdracht', 'detail'=>'Status, gebruiksgrens, beoordelaar, datum, ontvangers en bevestiging'],
        ];
    }

    /** Evidence references belong to the existing Office document store. */
    public static function observationFields(): array
    {
        return [
            'building_id','project_id','building_part','location','observed_at',
            'observer_operator_id','observation','source_reference','interpretation',
            'measurement','unit','measurement_method','measurement_uncertainty',
            'risk','consequence_of_delay','follow_up_action','evidence_refs',
        ];
    }

    /** Handbook V1-V6 describes permitted use of the opname, not the Lead/Deal stage. */
    public static function releaseStatuses(): array
    {
        return [
            'V1' => 'vrijgegeven',
            'V2' => 'voorwaardelijk',
            'V3' => 'aanvulling nodig',
            'V4' => 'fysieke verificatie',
            'V5' => 'specialistisch onderzoek',
            'V6' => 'niet vrijgegeven',
        ];
    }

    public static function applicability(): array
    {
        return ['open','gereed','niet_van_toepassing','onbekend'];
    }

    /** Optional trade sections are additive, not a limitation on future disciplines. */
    public static function validateDraft(array $data): array
    {
        $errors = [];
        if (isset($data['controls']) && !is_array($data['controls'])) {
            $errors['controls'] = 'Ongeldige controlelijst';
            return $errors;
        }
        foreach (($data['controls'] ?? []) as $key => $item) {
            if (!isset(self::controls()[$key]) || !is_array($item)
                || !in_array($item['status'] ?? null, self::applicability(), true)) {
                $errors['controls.'.$key] = 'Ongeldig controlepunt of status';
            }
        }
        if (isset($data['disciplines']) && (!is_array($data['disciplines'])
            || count(array_filter($data['disciplines'], 'is_string')) !== count($data['disciplines']))) {
            $errors['disciplines'] = 'Ongeldige disciplines';
        }
        if (isset($data['release_status']) && !array_key_exists((string)$data['release_status'], self::releaseStatuses())) {
            $errors['release_status'] = 'Ongeldige vrijgavestatus';
        }
        if (isset($data['observations'])) {
            if (!is_array($data['observations'])) {
                $errors['observations'] = 'Ongeldige bevindingenlijst';
            } else {
                foreach ($data['observations'] as $index => $observation) {
                    if (!is_array($observation) || array_diff(array_keys($observation), self::observationFields())
                        || (isset($observation['evidence_refs']) && (!is_array($observation['evidence_refs'])
                            || count(array_filter($observation['evidence_refs'], 'is_string')) !== count($observation['evidence_refs'])))) {
                        $errors['observations.'.$index] = 'Ongeldige bevinding';
                    }
                }
            }
        }
        return $errors;
    }
}
