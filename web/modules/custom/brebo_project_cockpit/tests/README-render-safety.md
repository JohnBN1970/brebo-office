# Project render safety

Project subpages must render independently from the heavy Project Cockpit steering builders.

The project cockpit module may add lightweight navigation in page preprocessing, but it must not build Finance, operational status, progress, milestones or rich-card data for every project subpage request.

This prevents one steering-domain exception from taking Planning, Documents, Budget, Procurement, Contracts, Invoices/Finance, Inzet, Quality or Completion down with it.
