import { env } from "cloudflare:test";
import { afterEach, describe, expect, it, vi } from "vitest";
import router from "../src/router";
import { hmacSha256Hex, sha256Hex } from "../src/crypto";

const path = "/v1/accounting/bank-data";
const secret = "test-shared-secret-with-sufficient-length";
const bankEnv = { ...env, AI: { toMarkdown: vi.fn() }, BREBO_SHARED_SECRET: secret, MONEYBIRD_BANK_READ_ENABLED: "enabled", MONEYBIRD_ADMINISTRATION_ID: "999", MONEYBIRD_ACCESS_TOKEN: "private-provider-token" };

async function signedRequest(method = "GET", timestamp = String(Math.floor(Date.now() / 1000))) {
  const requestId = crypto.randomUUID();
  const signature = await hmacSha256Hex(secret, [method, path, await sha256Hex(""), timestamp, requestId].join("\n"));
  return new Request("https://example.test" + path, { method, headers: {
    "X-BREBO-Request-Id": requestId, "X-BREBO-Timestamp": timestamp, "X-BREBO-Signature": "v1=" + signature,
  } });
}

describe("authenticated bank route through the deployed router", () => {
  afterEach(() => vi.unstubAllGlobals());

  it("returns only bank evidence and disables caching", async () => {
    const fetchMock = vi.fn().mockImplementation(async () => Response.json([]));
    vi.stubGlobal("fetch", fetchMock);
    const response = await router.fetch(await signedRequest(), bankEnv);
    expect(response.status).toBe(200);
    expect(response.headers.get("Cache-Control")).toBe("no-store, private");
    expect(await response.json()).toMatchObject({ status: "ok", provider: "moneybird", bank: { balances_available: false, period: "this_month", accounts: [], transactions: [] } });
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });

  it.each(["missing", "incorrect", "expired"])("rejects %s authentication before provider access", async kind => {
    const fetchMock = vi.fn();
    vi.stubGlobal("fetch", fetchMock);
    const request = kind === "missing" ? new Request("https://example.test" + path) : await signedRequest("GET", kind === "expired" ? "1" : undefined);
    if (kind === "incorrect") request.headers.set("X-BREBO-Signature", "v1=" + "0".repeat(64));
    const response = await router.fetch(request, bankEnv);
    expect(response.status).toBe(401);
    expect(response.headers.get("Cache-Control")).toContain("no-store");
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it.each(["", "short"])("fails closed for insufficient shared secret '%s'", async sharedSecret => {
    const fetchMock = vi.fn();
    vi.stubGlobal("fetch", fetchMock);
    expect((await router.fetch(await signedRequest(), { ...bankEnv, BREBO_SHARED_SECRET: sharedSecret })).status).toBe(503);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it.each([
    { MONEYBIRD_BANK_READ_ENABLED: undefined },
    { MONEYBIRD_BANK_READ_ENABLED: "disabled" },
    { MONEYBIRD_ACCESS_TOKEN: "" },
    { MONEYBIRD_ADMINISTRATION_ID: "REPLACE_IN_DEPLOYMENT" },
  ])("requires explicit enablement and provider configuration: %j", async overrides => {
    const fetchMock = vi.fn();
    vi.stubGlobal("fetch", fetchMock);
    const configured = { ...bankEnv, ...overrides };
    const response = await router.fetch(await signedRequest(), configured);
    expect(response.status).toBe(503);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it.each(["POST", "PATCH", "DELETE"])("refuses %s without calling Moneybird", async method => {
    const fetchMock = vi.fn();
    vi.stubGlobal("fetch", fetchMock);
    const response = await router.fetch(await signedRequest(method), bankEnv);
    expect(response.status).toBe(405);
    expect(response.headers.get("Allow")).toBe("GET");
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("refuses unsigned query parameters", async () => {
    const fetchMock = vi.fn();
    vi.stubGlobal("fetch", fetchMock);
    const request = await signedRequest();
    const response = await router.fetch(new Request(request.url + "?administration_id=other", request), bankEnv);
    expect(response.status).toBe(400);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it.each(["provider", "malformed", "timeout"])("returns a generic non-cacheable failure for %s errors", async kind => {
    const fetchMock = kind === "timeout" ? vi.fn().mockRejectedValue(new Error("private-bank-description"))
      : vi.fn().mockResolvedValue(kind === "provider" ? new Response("private-provider-token", { status: 403 }) : Response.json({ invalid: "private-bank-description" }));
    vi.stubGlobal("fetch", fetchMock);
    const response = await router.fetch(await signedRequest(), bankEnv);
    expect(response.status).toBe(502);
    expect(response.headers.get("Cache-Control")).toContain("no-store");
    const body = await response.text();
    expect(body).toContain("bank_read_failed");
    expect(body).not.toMatch(/private-provider-token|private-bank-description/);
  });
});
