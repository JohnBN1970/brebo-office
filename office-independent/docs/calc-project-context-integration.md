# Independent Office to Calc project-context integration

## Verified Calc consumer contract

Calc `apps/api/src/officeClient.ts` fetches:
`GET /api/workbench/v1/projects/{positive_integer}/calculation-context`

The server request uses `signedOfficeHeaders("GET", path)` and must not be replaced by a browser-provided header or a shared browser secret. The response must match:

```json
{
  "contract": "brebo-office-calc-context-v1",
  "project": {
    "id": 123,
    "code": "PROJECT-CODE",
    "title": "Project title",
    "status": "active",
    "client_name": "Client",
    "client_organization": null,
    "project_kind": "renovation",
    "disciplines": [],
    "description": "",
    "buildings": []
  }
}
```

`client_organization` can instead be an object with numeric `id` and string `title`; `buildings` is an array of numeric `id` and string `title`.

## Blocking integration requirements

1. Identify the **authoritative independent Office project record**. The newly introduced `office_calc_projects` table currently stores only a numeric ID; it is not a source for project title, client, status or buildings. Do not fabricate project context from CRM opportunities.
2. Implement a read-only adapter to that source. A missing, inactive or inaccessible project must fail closed.
3. Implement **server-to-server** request signature verification compatible with Calc `officeTransport.ts`; reject unsigned requests and expired/replayed signatures as appropriate. Do not use the browser Office session cookie for this API.
4. Return the exact v1 contract and matching requested project ID. Do not expose secrets or session tokens.
5. Add positive and negative integration tests (unknown project, wrong signature, mismatched ID, incomplete project data).
6. Register the endpoint in the sandbox router, run end-to-end Calc launch consumption, and only then evaluate live routing.

## Deployment separation

The independent Office public documentroot and private PHP source are separate from the Drupal platform. Do not overwrite live Office `index.php` or `.htaccess` without comparing deployed files. Calc's separate Node.js Hostinger app stays unchanged until a verified same-origin routing solution is available.

Current status: contract documented, endpoint **not implemented**, no production deployment.
