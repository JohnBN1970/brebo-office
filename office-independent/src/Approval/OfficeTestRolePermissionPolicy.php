<?php
declare(strict_types=1);
namespace Brebo\Office\Approval;

/**
 * First explicit, limited BREBO process-handbook role right:
 * ability to create a native project (not ability to view an arbitrary project).
 * Role previews are *never* an authorization grant; live ACL remains
 * office_module_permissions / office_project_memberships.
 */
final class OfficeTestRolePermissionPolicy {
    private const ROLES=[
        'actual'=>'Eigen account',
        'medewerker'=>'Medewerker',
        'RL-001'=>'Directie',
        'RL-002'=>'Senior Projectleider',
        'RL-003'=>'Senior Uitvoerder',
        'RL-004'=>'Senior Werkvoorbereider',
        'RL-005'=>'Calculator / Inkoper',
        'RL-006'=>'KAM / Kwaliteitsmanager',
        'RL-007'=>'Controller',
        'RL-008'=>'Administrateur',
        'RL-009'=>'Directiesecretaresse',
        'RL-010'=>'Planner / Capaciteitscoördinator',
        'RL-011'=>'Contract- en Documentcontroller',
        'RL-012'=>'Service- en Garantiecoördinator',
        'RL-013'=>'Omgevings- en Bewonerscoördinator',
        'RL-014'=>'Kennis- en Procesbeheerder',
        'RL-015'=>'BREBO Virtuele Staf',
        'RL-016'=>'Bedrijfsjurist / Juridisch Adviseur',
        'admin'=>'Systeembeheerder / Admin',
    ];
    public static function roles(): array { return self::ROLES; }

    /** "yes" is expected workflow privilege; "no" is outside role scope.
     * "actual" has no role policy: it is determined by database grants.
     */
    public static function expectedProjectCreate(string $role): ?bool {
        if ($role==='actual' || !array_key_exists($role,self::ROLES)) return null;
        // RG-007: R5 (technical administrator) never implies R4 or content authority.
        return in_array($role,['RL-001','RL-002'],true);
    }
}
