<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use DomainException;
use PDO;

/**
 * Canonical version-scoped heading storage. Caller must authorize access to
 * the draft and hold its collaboration revision lock before any write.
 */
final class OfficeCalcHeadingRepository
{
    public function __construct(private readonly PDO $db) {}

    public function add(int $versionId,string $system,string $code,string $title,int $sortOrder): int
    {
        require_once __DIR__.'/OfficeCalcHeadingCode.php';
        if ($versionId<1 || $sortOrder<0) throw new DomainException('Ongeldige hoofdstukpositie.');
        $heading=OfficeCalcHeadingCode::normalize($system,$code,$title);
        $q=$this->db->prepare('SELECT status FROM calculation_versions WHERE id=?');
        $q->execute([$versionId]);
        if ($q->fetchColumn()!=='draft') throw new DomainException('Calculatieversie is niet bewerkbaar.');
        $q=$this->db->prepare('INSERT INTO office_calc_version_headings(version_id,code_system,heading_code,title,sort_order) VALUES(?,?,?,?,?)');
        $q->execute([$versionId,$heading['system'],$heading['code'],$heading['title'],$sortOrder]);
        return (int)$this->db->lastInsertId();
    }

    public function assign(int $versionId,int $headingId,int $lineId): void
    {
        if ($versionId<1 || $headingId<1 || $lineId<1) throw new DomainException('Ongeldige hoofdstukkoppeling.');
        $q=$this->db->prepare('SELECT status FROM calculation_versions WHERE id=?');
        $q->execute([$versionId]);
        if ($q->fetchColumn()!=='draft') throw new DomainException('Calculatieversie is vergrendeld.');
        $q=$this->db->prepare('SELECT id FROM office_calc_version_headings WHERE id=? AND version_id=?');
        $q->execute([$headingId,$versionId]);
        if ($q->fetchColumn()===false) throw new DomainException('Hoofdgroep hoort niet bij deze versie.');
        $q=$this->db->prepare('SELECT id FROM calculation_lines WHERE id=? AND version_id=? AND line_type=?');
        $q->execute([$lineId,$versionId,'item']);
        if ($q->fetchColumn()===false) throw new DomainException('Calculatieregel hoort niet bij deze versie.');
        $q=$this->db->prepare('DELETE FROM office_calc_heading_line_links WHERE calculation_line_id=?');
        $q->execute([$lineId]);
        $q=$this->db->prepare('INSERT INTO office_calc_heading_line_links(heading_id,calculation_line_id) VALUES(?,?)');
        $q->execute([$headingId,$lineId]);
    }

    public function list(int $versionId): array
    {
        $q=$this->db->prepare('SELECT id,code_system,heading_code,title,sort_order FROM office_calc_version_headings WHERE version_id=? ORDER BY sort_order,id');
        $q->execute([$versionId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
