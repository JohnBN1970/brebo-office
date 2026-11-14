<?php
declare(strict_types=1);

namespace Brebo\Office\Crm;

use InvalidArgumentException;
use PDO;

/**
 * Read-only CRM relation candidates for a mail sender.
 * The caller must supply the existing CRM relation authorization policy.
 * An email match is a proposal, never permission to link or create records.
 */
final class OfficeCrmMailRelationCandidates
{
    public function __construct(private readonly PDO $db) {}

    /**
     * @param callable(string):bool $canViewRelation
     * @return list<array{id:string,display_name:string,email:string,reason:string}>
     */
    public function forSender(string $sender, callable $canViewRelation, int $limit = 10): array
    {
        if ($limit < 1 || $limit > 25 || strlen($sender) > 1000) {
            throw new InvalidArgumentException('invalid_crm_mail_candidate_query');
        }
        // Accept a plain address or a conventional display-name <address> header.
        $address = trim($sender);
        if (preg_match('/<([^<>]+)>/', $address, $matches) === 1) {
            $address = trim($matches[1]);
        }
        if (!filter_var($address, FILTER_VALIDATE_EMAIL) || strlen($address) > 254) {
            return [];
        }
        $address = strtolower($address);
        $stmt = $this->db->prepare("SELECT id,display_name,email
            FROM office_crm_relations
            WHERE status='active' AND LOWER(TRIM(email))=:email
            ORDER BY id DESC LIMIT 250");
        $stmt->execute([':email'=>$address]);
        $result=[];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id=(string)$row['id'];
            if (!$canViewRelation($id)) continue;
            $result[]=[
                'id'=>$id,
                'display_name'=>(string)$row['display_name'],
                'email'=>(string)$row['email'],
                'reason'=>'E-mailadres afzender komt overeen met CRM-relatie'
            ];
            if (count($result)>=$limit) break;
        }
        return $result;
    }
}
