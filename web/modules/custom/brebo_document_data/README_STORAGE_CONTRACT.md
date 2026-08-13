# BREBO document storage contract

The dossier model and physical binary storage are independent.

- `document_id` is the stable application identity.
- `storage_provider` selects the physical provider.
- `storage_key` identifies the binary inside that provider.
- Project/building/CRM names never form part of physical identity.
- Original source binaries are immutable and must never be destructively recompressed.
- `source_mailbox` means the original is still retrievable from its authoritative mail source.
- `drupal_private` / `local_private` represent private local storage.
- `s3`, `r2` and `s3_compatible` are reserved for scalable object storage.
- Previews and thumbnails are derivatives and may be optimized separately.

Provider-specific binary delivery is intentionally a later adapter layer; dossier and revision code must only depend on this neutral contract.
