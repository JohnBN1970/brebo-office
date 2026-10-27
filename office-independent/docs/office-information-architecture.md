# Office information architecture — user-approved structure

The same structure applies across the application, not only to Inzet or one project.

Main order: **Bedrijfsoverzicht → CRM → Projecten → Calculatie & Offertes → Finance → Informatie**.

The company overview steers across projects. Project context scopes work to one authorized project and remains visible while moving between relevant areas. Global and project entry points use the same underlying records; do not create duplicate calculation, offer, relation or financial masters.

GOTIK order for company/project steering: **Geld → Organisatie → Tijd → Informatie → Kwaliteit**.

- CRM owns relations: customers, suppliers, contacts, requests and opportunities. A supplier is a relation type, not an Inzet navigation area.
- Project organisation owns responsibility, people, Inzet and OnSite. Supplier employee evidence and hour review remain project work.
- Calculations and offers have both a global entry and a project entry. The latter retains the project ID. They connect to project Geld/budget without duplicating their records.
- Finance owns obligations, invoice review, payments, forecasts and results. Navigation never grants financial data or execution permissions.
- Information owns company knowledge/documents and project-scoped information. Project documents retain project access controls.
- Time and quality maintain their own verified sources. Missing sources must be explicitly unavailable, never represented as zero, compliant, complete or live functionality.

`OfficeNavigation` is the shared main/context presentation contract. Its HTML response adapter allows the existing protected controllers and custom runtime to keep their own session checks, CSRF forms, scripts and storage. It runs only on successful Office-test GET HTML; it does not modify login, denial, POST, API/JSON or production responses. Main menu rendering is not authorization.

The three area entry points are read-only navigation pages with explicit availability notices. They list only projects belonging to the active authenticated operator. A project-specific entry requires that same project membership; unknown/unauthorized projects return 404. They do not claim that calculation, finance or document workflows have been implemented.

Inzet's supplier-week review belongs to Controle. The obsolete Leveranciers tab is removed; its old tab parameter maps to Controle so review work is preserved. Company-wide Inzet remains an organisation shortcut, not a seventh top-level module.
