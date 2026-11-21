<?php
declare(strict_types=1);
namespace Brebo\Office\Process;

/**
 * FR-201 v1.0 source contract. No database writes or authorization shortcuts:
 * a CRM controller must authenticate, check ACL/CSRF and persist through the
 * existing CRM domain repository. This class validates the form payload only.
 */
final class OfficeFr201Intake
{
    public const CODE = 'FR-201';
    public const VERSION = '1.0';
    public const PROCESS = 'PR-200';

    /** @return array<string, array{label:string,required:bool}> */
    public static function fields(): array
    {
        return [
            'intake_id' => ['label' => 'Intake-ID (INT-YYYY-NNNN)', 'required' => true],
            'received_at' => ['label' => 'Ontvangstdatum en -tijd', 'required' => true],
            'channel' => ['label' => 'Ontvangstkanaal', 'required' => true],
            'received_by' => ['label' => 'Ontvanger', 'required' => true],
            'source_reference' => ['label' => 'Bronverwijzing', 'required' => true],
            'relation_id' => ['label' => 'REL-ID', 'required' => true],
            'contract_party' => ['label' => 'Juridische contractpartij', 'required' => true],
            'contact_name' => ['label' => 'Contactpersoon', 'required' => true],
            'contact_role' => ['label' => 'Functie contactpersoon', 'required' => true],
            'contact_authority' => ['label' => 'Bevoegdheid contactpersoon', 'required' => true],
            'contact_phone' => ['label' => 'Telefoon', 'required' => true],
            'contact_email' => ['label' => 'E-mail', 'required' => true],
            'location' => ['label' => 'Project / adres / object', 'required' => true],
            'customer_question' => ['label' => 'Klantvraag in eigen woorden', 'required' => true],
            'desired_result' => ['label' => 'Gewenst resultaat', 'required' => true],
            'scope' => ['label' => 'Werksoorten en scope', 'required' => true],
            'reason' => ['label' => 'Aanleiding', 'required' => true],
            'planning' => ['label' => 'Planning en termijnen', 'required' => true],
            'budget' => ['label' => 'Budget / prijsbasis (indien bekend)', 'required' => false],
            'uncertainties' => ['label' => 'Ontbrekende gegevens en onzekerheden', 'required' => false],
            'owner_user_id' => ['label' => 'Bevoegde eigenaar (Office-gebruikers-ID)', 'required' => true],
            'next_action' => ['label' => 'Vervolgactie', 'required' => true],
        ];
    }

    /** @return array<string,string> Validation errors keyed by field */
    public static function validate(array $data): array
    {
        $errors = [];
        foreach (self::fields() as $key => $field) {
            $value = $data[$key] ?? null;
            if ($field['required'] && (!is_scalar($value) || trim((string)$value) === '')) {
                $errors[$key] = 'Verplicht veld';
            } elseif ($value !== null && !is_scalar($value)) {
                $errors[$key] = 'Ongeldige waarde';
            }
        }
        if (isset($data['intake_id']) && !preg_match('/^INT-[0-9]{4}-[0-9]{4}$/D', (string)$data['intake_id'])) {
            $errors['intake_id'] = 'Verwacht INT-YYYY-NNNN';
        }
        if (isset($data['contact_email']) && trim((string)$data['contact_email']) !== ''
            && filter_var((string)$data['contact_email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['contact_email'] = 'Ongeldig e-mailadres';
        }
        if (isset($data['owner_user_id']) && filter_var($data['owner_user_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $errors['owner_user_id'] = 'Selecteer een geldige Office-gebruiker';
        }
        return $errors;
    }
}
