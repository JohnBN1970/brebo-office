# BREBO intake managed extraction preflight

The production intake document preflight treats the Cloudflare-managed document extraction provider as the required primary runtime for PDF/JPEG/PNG/WebP extraction.

Required production acceptance:

- Drupal bootstraps on the deployed production SHA.
- `brebo_data_intake.managed_document_text_extraction_provider` exists.
- `brebo_data_intake.managed_document_text_enricher` exists.
- The managed provider is runtime-configured and reports support for PDF and PNG.
- Local `pdftotext`, `pdftoppm`, and Tesseract discovery remains visible in the log but is optional fallback capability and does not determine PASS/FAIL.
- Deployment identity is revalidated before PASS is emitted.

The Integration API workflow separately proves the authenticated Cloudflare document-extraction endpoint end to end.
