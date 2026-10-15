# Office project access: beperkte eerste slice

De bestaande `brebo_project` blijft canoniek. `OfficeProjectIdentity` is alleen een verwijzing naar dat project.

`OfficeProjectAccess::canRead()` controleert uitsluitend of een actieve Office-operator een bestaande `office_calc_project_access`-grant heeft. Dat is een **Calc-gerelateerde projecttoegang**, geen algemene autorisatie voor Projecten, Gebouwen, Finance, Inkoop of documenten. Zonder geldige grant of bij databasefout is de uitkomst `false`.

De klasse mag niet worden gebruikt om algemene projectlijsten of projectdossiers zichtbaar te maken. Voor die functies moet eerst een afzonderlijk projectrechtencontract worden vastgesteld en getest, inclusief eigenaar/rol en objectscope.

Geen projectdatabase, schema, migratie, dashboardtegel, router of productieconfiguratie is in deze PR aangepast. De gebruiker krijgt hierdoor nog geen extra toegang.
