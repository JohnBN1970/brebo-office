<?php
declare(strict_types=1);
 $groups=[
 'Projecten'=>[['OFFICE_NATIVE_PROJECTS_ENABLED','OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED'],['office_projects','office_project_buildings']],
 'Gebouwen en woningtypen'=>[[],['office_buildings','office_housing_types','office_project_housing_types','office_project_calculation_scopes','office_scope_quantity_lines']],
 'Startdossier'=>[['OFFICE_TEST_PROJECT_KICKSTART_ENABLED'],[]],
 'Planning en inzet'=>[['OFFICE_TEST_INZET_SHIFTS_ENABLED','OFFICE_TEST_INZET_SHIFTS_SCHEMA_VERIFIED','OFFICE_TEST_INZET_SHIFT_WRITE_ENABLED'],[]],
 'OnSite'=>[['OFFICE_ONSITE_SCHEMA_VERIFIED','OFFICE_TEST_PRESENCE_REVIEW_ENABLED','OFFICE_TEST_PROJECT_MAP_COORDINATES_VERIFIED','OFFICE_TEST_ZONE_WRITE_ENABLED','OFFICE_TEST_BUILDING_ZONE_SCHEMA_VERIFIED'],[]],
 'Urencontrole'=>[['OFFICE_TEST_ONSITE_HOURS_APPROVAL_ENABLED','OFFICE_TEST_ONSITE_FOUR_EYES_REQUIRED','OFFICE_TEST_ONSITE_REJECTION_SCHEMA_VERIFIED'],['office_onsite_hours_decisions']],
 'Werkbegroting en overdracht'=>[[],['office_calc_crm_acceptance_transfers']],
 'CRM en relaties'=>[['OFFICE_TEST_CRM_ENABLED'],[]],
 'Intake en leads'=>[[],[]],
 'Deals'=>[[],[]],
 'Calculatie'=>[['OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED','OFFICE_CALC_CRM_SCHEMA_VERIFIED'],['office_calc_crm_drafts']],
 'Offertestudio'=>[[],[]],
 'Inkoop'=>[[],[]],
 'FinanceCenter'=>[[],['office_finance_invoice_lines','office_finance_invoice_documents','office_finance_cost_rates']],
 'Documenten'=>[[],[]],
 'E-mail'=>[[],[]],
 'Kennis en kwaliteit'=>[[],[]],
 'Oplevering en snagging'=>[[],[]]];
 $routes=[
 'Projecten'=>['/office-test/projecten','office-projects.php'],
 'Gebouwen en woningtypen'=>['/office-test/gebouwen','office-buildings.php'],
 'Planning en inzet'=>['/office-test/inzet/dienst/nieuw','office-inzet-shift-create.php'],
 'OnSite'=>['/office-test/inzet/onsite/aanwezigheid','office-onsite-presence-review.php'],
 'Urencontrole'=>['/office-test/inzet/onsite/uren-beoordelen','office-onsite-presence-review.php'],
 'CRM en relaties'=>['/office-test/crm/relaties','office-crm-relations.php'],
 'Intake en leads'=>['/office-test/crm/aanvragen','crm-new-requests.php'],
 'Deals'=>['/office-test/crm/verkoopkansen','office-crm-opportunities.php'],
 'Calculatie'=>['/office-test/calculatie','office-calc-crm-workbench.php'],
 'Inkoop'=>['/office-test/inkoop','office-governance.php'],
 'FinanceCenter'=>['/office-test/finance/administratie','office-finance-center.php'],
 'E-mail'=>['/office-test/email','office-email.php'],
 'Kennis en kwaliteit'=>['/office-test/kwaliteit','office-governance.php']];
 $schemas=[
 'Projecten'=>['office_projects'=>['id'],'office_project_buildings'=>['project_id','building_id'],'office_project_memberships'=>['project_id','operator_id','permission']],
 'Gebouwen en woningtypen'=>['office_buildings'=>['id','building_name'],'office_housing_types'=>['id','building_id','code','name'],'office_project_housing_types'=>['project_id','building_id','housing_type_id','dwelling_count']],
 'CRM en relaties'=>['office_crm_relations'=>['id','display_name','status'],'office_crm_relation_roles'=>['relation_id','role_code']],
 'Intake en leads'=>['office_crm_leads'=>[]],
 'Deals'=>['office_crm_opportunities'=>['id','relation_id','title','revision','stage','status'],'office_crm_deal_buildings'=>[]],
 'Calculatie'=>['office_calc_crm_drafts'=>['id','created_by','opportunity_id','calculation_id','crm_revision','project_id'],'calculations'=>['id','office_calculation_id'],'calculation_versions'=>['id','calculation_id','version_no'],'calculation_lines'=>['version_id','quantity','calc_net_quantity','calc_material_loss_percent','calc_extra_labor_hours','calc_quantity_source','calc_quantity_source_ref']],
 'Werkbegroting en overdracht'=>['office_calc_crm_acceptance_transfers'=>['crm_opportunity_id','calculation_id','accepted_revision','office_project_id'],'office_calc_work_budget_lines'=>[]],
 'Inkoop'=>['office_procurement_orders'=>['project_id','building_id']],
 'Kennis en kwaliteit'=>['office_quality_checks'=>['project_id','building_id'],'office_risks'=>['project_id','building_id']]];
 $dependencies=[
 'Deals'=>['CRM en relaties','Gebouwen en woningtypen'],
 'Calculatie'=>['Deals','Gebouwen en woningtypen'],
 'Werkbegroting en overdracht'=>['Calculatie','Projecten'],
 'Planning en inzet'=>['Projecten'],
 'OnSite'=>['Planning en inzet','Gebouwen en woningtypen'],
 'Urencontrole'=>['OnSite'],
 'Inkoop'=>['Projecten','Werkbegroting en overdracht'],
 'Kennis en kwaliteit'=>['Projecten','Gebouwen en woningtypen'],
 'FinanceCenter'=>['Projecten','Werkbegroting en overdracht']];
return [$groups,$routes,$schemas,$dependencies];
