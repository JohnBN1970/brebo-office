import { SELF } from "cloudflare:test";
import { describe, expect, it } from "vitest";

const secret = "test-shared-secret-with-sufficient-length";

describe("BREBO Integration API", () => {
  it("returns sanitized health status for a valid signature", async () => {
    const requestId = crypto.randomUUID();
    const response = await SELF.fetch("https://example.test/health/status", {
      headers: await signedHeaders("GET", "/health/status", "", requestId),
    });
    expect(response.status).toBe(200);
    expect(await response.json()).toEqual({
      status: "healthy",
      api_version: "1.0.0-draft",
      worker_version: "0.1.0",
    });
  });

  it("rejects a missing signature", async () => {
    const response = await SELF.fetch("https://example.test/v1/communications/analyze", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(validRequest()),
    });
    expect(response.status).toBe(401);
  });

  it("rejects an expired timestamp", async () => {
    const body = JSON.stringify(validRequest());
    const requestId = crypto.randomUUID();
    const response = await SELF.fetch("https://example.test/v1/communications/analyze", {
      method: "POST",
      headers: await signedHeaders(
        "POST",
        "/v1/communications/analyze",
        body,
        requestId,
        Math.floor(Date.now() / 1_000) - 301,
      ),
      body,
    });
    expect(response.status).toBe(401);
  });

  it("rejects test mode containing real data", async () => {
    const body = JSON.stringify(validRequest({ contains_real_data: true }));
    const response = await signedAnalyze(body);
    expect(response.status).toBe(422);
  });

  it("returns a safe analysis and blocks replay", async () => {
    const body = JSON.stringify(validRequest());
    const requestId = crypto.randomUUID();
    const headers = await signedHeaders("POST", "/v1/communications/analyze", body, requestId);

    const first = await SELF.fetch("https://example.test/v1/communications/analyze", {
      method: "POST",
      headers,
      body,
    });
    expect(first.status).toBe(200);
    expect(await first.json()).toMatchObject({ stored: false, sent: false, request_id: requestId });

    const second = await SELF.fetch("https://example.test/v1/communications/analyze", {
      method: "POST",
      headers,
      body,
    });
    expect(second.status).toBe(409);
  });

  it("maps invalid provider output to a generic 502", async () => {
    const request = validRequest();
    request.communication.subject = "Providerfout";
    const response = await signedAnalyze(JSON.stringify(request));
    expect(response.status).toBe(502);
    expect(JSON.stringify(await response.json())).not.toContain("provider output");
  });
});

function validRequest(overrides: Record<string, unknown> = {}) {
  return {
    test_mode: true,
    contains_real_data: false,
    human_review_required: true,
    communication: {
      id: null,
      project_id: null,
      channel: "test",
      subject: "Fictieve test",
      message: "Dit is uitsluitend fictieve testcommunicatie.",
    },
    ...overrides,
  };
}

async function signedAnalyze(body: string): Promise<Response> {
  const requestId = crypto.randomUUID();
  return SELF.fetch("https://example.test/v1/communications/analyze", {
    method: "POST",
    headers: await signedHeaders("POST", "/v1/communications/analyze", body, requestId),
    body,
  });
}

async function signedHeaders(
  method: string,
  path: string,
  body: string,
  requestId: string,
  timestamp = Math.floor(Date.now() / 1_000),
): Promise<Record<string, string>> {
  const bodyHash = await digest(body);
  const canonical = [method, path, bodyHash, String(timestamp), requestId].join("\n");
  const key = await crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );
  const signature = await crypto.subtle.sign("HMAC", key, new TextEncoder().encode(canonical));
  return {
    "Content-Type": "application/json",
    "X-BREBO-Timestamp": String(timestamp),
    "X-BREBO-Request-Id": requestId,
    "X-BREBO-Signature": `v1=${hex(new Uint8Array(signature))}`,
  };
}

async function digest(value: string): Promise<string> {
  const bytes = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(value));
  return hex(new Uint8Array(bytes));
}

function hex(bytes: Uint8Array): string {
  return Array.from(bytes, (byte) => byte.toString(16).padStart(2, "0")).join("");
}
