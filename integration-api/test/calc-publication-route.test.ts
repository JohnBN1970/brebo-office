import { describe, expect, it, vi } from "vitest";
import { calcPublication } from "../src/calc-publication-route";
import { hmacSha256Hex, sha256Hex } from "../src/crypto";

const secret = "test-secret-with-more-than-thirty-two-characters";
const path = "/api/workbench/v2/calculations/41/calc-results";
const requestId = "c420d632-5e42-4ad9-b31c-0f51c4c7be21";
const body = JSON.stringify({
  actor_id: 5, office_version: "office-1", calc_version: "calc-1",
  commercial_summary: { purchase: 100, sales: 150, margin: 50, margin_pct: 33.33, vat: 31.5, total_incl_vat: 181.5, vat_breakdown: [] },
});

async function signedRequest(method: "GET" | "POST", text = method === "GET" ? "" : body) {
  const timestamp = String(Math.floor(Date.now() / 1000));
  const canonical = [method, path, await sha256Hex(text), timestamp, requestId, ...(method === "GET" ? ["5"] : [])].join("\n");
  return new Request("https://office.example" + path, {
    method, headers: { "X-BREBO-Request-Id": requestId, "X-BREBO-Timestamp": timestamp, ...(method === "GET" ? { "X-BREBO-Actor-Id": "5" } : {}), "X-BREBO-Signature": "v1=" + await hmacSha256Hex(secret, canonical), ...(method === "POST" ? { "Content-Type": "application/json" } : {}) },
    ...(method === "POST" ? { body: text } : {}),
  });
}

describe("standalone Calc publication security contract", () => {
  it("fails closed when the shared secret is not configured", async () => {
    const response = await calcPublication(await signedRequest("GET"), { BREBO_SHARED_SECRET: "" } as Env);
    expect(response.status).toBe(503);
    expect((await response.json() as { error: { code: string } }).error.code).toBe("publication_auth_not_configured");
  });
  it("rejects an invalid signature before touching storage", async () => {
    const request = await signedRequest("POST");
    request.headers.set("X-BREBO-Signature", "v1=" + "0".repeat(64));
    const response = await calcPublication(request, { BREBO_SHARED_SECRET: secret } as Env);
    expect(response.status).toBe(401);
  });
  it("rejects an oversized declared payload", async () => {
    const request = await signedRequest("POST");
    request.headers.set("Content-Length", "32769");
    const response = await calcPublication(request, { BREBO_SHARED_SECRET: secret } as Env);
    expect(response.status).toBe(413);
  });
  it("fails closed without an authorization binding even with valid HMAC", async () => {
    const response = await calcPublication(await signedRequest("GET"), { BREBO_SHARED_SECRET: secret } as Env);
    expect(response.status).toBe(503);
    expect((await response.json() as { error: { code: string } }).error.code).toBe("publication_access_not_configured");
  });
  it("denies reads when the access provider rejects the calculation", async () => {
    const latest = vi.fn();
    const env = { BREBO_SHARED_SECRET: secret, CALC_ACCESS_REGISTRY: { getByName: () => ({ find: async () => null }) }, CALC_PUBLICATION_STORE: { getByName: () => ({ latest }) } } as unknown as Env;
    const response = await calcPublication(await signedRequest("GET"), env);
    expect(response.status).toBe(403);
    expect(latest).not.toHaveBeenCalled();
  });
  it("denies publications before consuming replay state or writing snapshots", async () => {
    const publish = vi.fn();
    const useOnce = vi.fn();
    const env = { BREBO_SHARED_SECRET: secret, CALC_ACCESS_REGISTRY: { getByName: () => ({ find: async () => null }) }, REPLAY_GUARD: { getByName: () => ({ useOnce }) }, CALC_PUBLICATION_STORE: { getByName: () => ({ publish }) } } as unknown as Env;
    const response = await calcPublication(await signedRequest("POST"), env);
    expect(response.status).toBe(403);
    expect(useOnce).not.toHaveBeenCalled();
    expect(publish).not.toHaveBeenCalled();
  });
  it("rejects tampered GET actor identity", async () => {
    const request = await signedRequest("GET");
    request.headers.set("X-BREBO-Actor-Id", "7");
    const response = await calcPublication(request, { BREBO_SHARED_SECRET: secret } as Env);
    expect(response.status).toBe(401);
  });
  it("passes the signed GET actor to the calculation authorization provider", async () => {
    const find = vi.fn().mockResolvedValue({ calculation_id: 41, active: true, readers: [7], publishers: [] });
    const latest = vi.fn();
    const env = { BREBO_SHARED_SECRET: secret, CALC_ACCESS_REGISTRY: { getByName: () => ({ find }) }, CALC_PUBLICATION_STORE: { getByName: () => ({ latest }) } } as unknown as Env;
    const response = await calcPublication(await signedRequest("GET"), env);
    expect(response.status).toBe(403);
    expect(find).toHaveBeenCalledWith(41);
    expect(latest).not.toHaveBeenCalled();
  });
  it("authenticates and reads the latest snapshot", async () => {
    const latest = vi.fn().mockResolvedValue({ snapshot_id: 2, content_hash: "a".repeat(64), published_by: 5, published_at: 123, payload: { office_version: "office-1", calc_version: "calc-1", commercial_summary: { sales: 150 } } });
    const env = { BREBO_SHARED_SECRET: secret, CALC_ACCESS_REGISTRY: { getByName: () => ({ find: async () => ({ calculation_id: 41, active: true, readers: [], publishers: [5] }) }) }, CALC_PUBLICATION_STORE: { getByName: () => ({ latest }) } } as unknown as Env;
    const response = await calcPublication(await signedRequest("GET"), env);
    expect(response.status).toBe(200);
    expect(latest).toHaveBeenCalledOnce();
    const result = await response.json() as { commercial_summary: { sales: number }; office_version: string };
    expect(result.commercial_summary.sales).toBe(150);
    expect(result.office_version).toBe("office-1");
  });
});
