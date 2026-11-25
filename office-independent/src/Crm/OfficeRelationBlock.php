<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

use PDO;

/**
 * Shared, read-only relation context for Office modules.
 * Call only after the hosting module has authenticated and authorized the operator.
 * No implicit access grant or writes.
 */
final class OfficeRelationBlock
{
    public static function render(PDO $db, int $relationId, bool $showContacts = true): string
    {
        if ($relationId < 1) {
            return '';
        }
        $stmt = $db->prepare('SELECT id, display_name, relation_kind, status, email, phone, city FROM office_crm_relations WHERE id = ? LIMIT 1');
        $stmt->execute([$relationId]);
        $relation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$relation) {
            return '<section class="office-relation-block"><p>Relatie niet gevonden.</p></section>';
        }
        $h = static fn($v): string => htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $url = '/office-test/crm/relaties/detail?id=' . (int)$relation['id'];
        $html = '<section class="office-relation-block" aria-label="Relatie">';
        $html .= '<h3>Relatie</h3><p><a href="' . $h($url) . '">' . $h($relation['display_name']) . '</a></p>';
        $html .= '<p>' . $h($relation['relation_kind'] === 'company' ? 'Bedrijf' : 'Persoon') . ' · ' . $h($relation['status']) . '</p>';
        foreach (['email' => 'E-mail', 'phone' => 'Telefoon', 'city' => 'Plaats'] as $key => $label) {
            if (!empty($relation[$key])) {
                $html .= '<p><strong>' . $label . ':</strong> ' . $h($relation[$key]) . '</p>';
            }
        }
        if ($showContacts) {
            $stmt = $db->prepare("SELECT p.given_name, p.family_name, p.email, c.job_title, c.is_primary
                FROM office_crm_relation_contacts c
                JOIN office_crm_contact_people p ON p.id = c.contact_id
                WHERE c.relation_id = ? AND p.status = 'active'
                ORDER BY c.is_primary DESC, p.family_name, p.given_name LIMIT 50");
            $stmt->execute([$relationId]);
            $html .= '<details><summary>Contactpersonen</summary><ul>';
            $count = 0;
            while ($person = $stmt->fetch(PDO::FETCH_ASSOC)) {
                ++$count;
                $name = trim($person['given_name'] . ' ' . $person['family_name']);
                $html .= '<li>' . $h($name);
                if ($person['job_title']) $html .= ' — ' . $h($person['job_title']);
                if ($person['is_primary']) $html .= ' (primair)';
                if ($person['email']) $html .= ' · ' . $h($person['email']);
                $html .= '</li>';
            }
            if ($count === 0) $html .= '<li>Geen contactpersonen</li>';
            $html .= '</ul></details>';
        }
        return $html . '</section>';
    }
}
