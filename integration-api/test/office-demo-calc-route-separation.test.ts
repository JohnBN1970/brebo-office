import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";

const root = fileURLToPath(new URL("../../", import.meta.url));
const source = readFileSync(new URL("../src/calc-publication-route.ts", import.meta.url), "utf8");
const router = readFileSync(new URL("../src/router.ts", import.meta.url), "utf8");
const handoff = readFileSync(new URL("../../office-independent/src/Demo/OfficeDemoCalcV2Boundary.php", import.meta.url), "utf8");

test("Calc results route is publication-only and not an Office quantity import", () => {
  assert.match(source, /\/calc-results/);
  assert.match(source, /request\.method !== "POST" && request\.method !== "GET"/);
  assert.match(router, /calcPublication\(request, env\)/);
  assert.doesNotMatch(source, /quantity_per_dwelling|source_finding_code/);
});

test("Demo handoff remains blocked until canonical Calc identity and access are proven", () => {
  assert.match(handoff, /BLOCKED_CALC_AUTHORITY_REQUIRED/);
  assert.match(handoff, /BLOCKED_CALC_ACCESS_VERIFICATION_REQUIRED/);
  assert.match(handoff, /'external_write_allowed'=>false/);
});
