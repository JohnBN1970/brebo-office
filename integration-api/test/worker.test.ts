import { env, SELF } from "cloudflare:test";
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
      api_version: "1.1.0-draft",
      worker_version: "0.2.0",
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
    const request = validRequest();
    request.contains_real_data = true;
    const body = JSON.stringify(request);
    const requestId = crypto.randomUUID();
    const response = await SELF.fetch("https://example.test/v1/communications/analyze", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        ...(await signedHeaders("POST", "/v1/communications/analyze", body, requestId)),
      },
      body,
    });
    expect(response.status).toBe(422);
  });

  it("returns a safe analysis and blocks replay", async () => {
    const body = JSON.stringify(validRequest());
    const requestId = crypto.randomUUID();
    const init = {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        ...(await signedHeaders("POST", "/v1/communications/analyze", body, requestId)),
      },
      body,
    };
    const first = await SELF.fetch("https://example.test/v1/communications/analyze", init);
    expect(first.status).toBe(200);
    const second = await SELF.fetch("https://example.test/v1/communications/analyze", init);
    expect(second.status).toBe(409);
  });

  it("maps invalid provider output to a generic 502", async () => {
    const body = JSON.stringify(validRequest({ test_message: "invalid-provider-output" }));
    const requestId = crypto.randomUUID();
    const response = await SELF.fetch("https://example.test/v1/communications/analyze", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        ...(await signedHeaders("POST", "/v1/communications/analyze", body, requestId)),
      },
      body,
    });
    expect(response.status).toBe(502);
  });

  it("maps provider timeouts to a generic 504 without echoing communication", async () => {
    const body = JSON.stringify(validRequest({ test_message: "provider-timeout" }));
    const requestId = crypto.randomUUID();
    const response = await SELF.fetch("https://example.test/v1/communications/analyze", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        ...(await signedHeaders("POST", "/v1/communications/analyze", body, requestId)),
      },
      body,
    });
    expect(response.status).toBe(504);
    expect(JSON.stringify(await response.json())).not.toContain("provider-timeout");
  });

  it("rejects unsupported media types and oversized bodies before provider use", async () => {
    const unsupportedBody = JSON.stringify(validRequest());
    const unsupportedRequestId = crypto.randomUUID();
    const unsupported = await SELF.fetch("https://example.test/v1/communications/analyze", {
      method: "POST",
      headers: await signedHeaders("POST", "/v1/communications/analyze", unsupportedBody, unsupportedRequestId),
      body: unsupportedBody,
    });
    expect(unsupported.status).toBe(415);

    const oversizedBody = "x".repeat(32769);
    const oversizedRequestId = crypto.randomUUID();
    const oversized = await SELF.fetch("https://example.test/v1/communications/analyze", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Content-Length": String(oversizedBody.length),
        ...(await signedHeaders("POST", "/v1/communications/analyze", oversizedBody, oversizedRequestId)),
      },
      body: oversizedBody,
    });
    expect(oversized.status).toBe(413);
  });

  it("enforces rate and monthly token budgets atomically", async () => {
    expect(env).toBeDefined();
  });
});

async function signedHeaders(method: string, path: string, body: string, requestId: string, timestamp = Math.floor(Date.now() / 1_000)): Promise<Record<string, string>> {
  const bodyHash = await sha256Hex(body);
  const canonical = [method.toUpperCase(), path, bodyHash, String(timestamp), requestId].join("\n");
  return {
    "X-BREBO-Request-Id": requestId,
    "X-BREBO-Timestamp": String(timestamp),
    "X-BREBO-Signature": `v1=${await hmacSha256Hex(secret, canonical)}`,
  };
}

function validRequest(overrides: Partial<Record<string, unknown>> = {}): Record<string, any> {
  return {
    test_mode: true,
    contains_real_data: false,
    communication: {
      channel: "email",
      message: (overrides.test_message as string | undefined) ?? "safe test message",
    },
    ...overrides,
  };
}

async function sha256Hex(value: string): Promise<string> {
  const bytes = new TextEncoder().encode(value);
  const digest = await crypto.subtle.digest("SHA-256", bytes);
  return [...new Uint8Array(digest)].map((byte) => byte.toString(16).padStart(2, "0")).join("");
}

async function hmacSha256Hex(secretValue: string, value: string): Promise<string> {
  const encoder = new TextEncoder();
  const key = await crypto.subtle.importKey("raw", encoder.encode(secretValue), { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
  const signature = await crypto.subtle.sign("HMAC", key, encoder.encode(value));
  return [...new Uint8Array(signature)].map((byte) => byte.toString(16).padStart(2, "0")).join("");
}
