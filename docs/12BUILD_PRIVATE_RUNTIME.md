# 12Build private runtime configuration

The connector code is versioned in Git. Credentials and runtime tokens must not be.

## Hostinger configuration

Store a PHP file **outside** the domain document root and outside any Git-managed or deployment-reconciled directory, readable only by the PHP service user. Example contents (placeholders only):

```php
<?php
return [
    'login' => '12build-account@example.invalid',
    'api_key' => 'REPLACE_ON_HOSTINGER_ONLY',
    'base_url' => 'https://app.12build.com/nl_NL/rest/bs_1.0/api',
];
```

Use a private absolute path selected for the actual Hostinger account. Set file mode 0600 (or the least permissive mode compatible with the PHP runtime), ensure it is not web-accessible, and exclude it from backups/log exports accessible to unauthorized users. Point the runtime to the path using `TWELVEBUILD_CONFIG_PATH`; do not put credential values in environment variables or CI logs.

Do not deploy a template containing real credentials. Never commit credentials, tokens, response dumps containing private data, or real invitations.

The existing GitHub Actions secrets `TWELVEBUILD_API_KEY` and `TWELVEBUILD_LOGIN` were manually created earlier. **They are not needed for the final Hostinger runtime model.** Remove them from GitHub once Hostinger configuration has been verified and a secure migration is complete. Rotate the key if exposure is suspected.

## Integration status

This is a configuration loader and HTTP client foundation, **not** a complete import. Verify authentication response and invitation schema with official documentation and a read-only smoke test before enabling polling. Do not auto-create CRM records until deduplication, access controls, audit and mapping have passed acceptance. Production remains untouched.
