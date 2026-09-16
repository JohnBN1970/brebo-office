import { orderDraftRequestSchema } from "./contracts";
import { fixedTimeEqual, hmacSha256Hex, sha256Hex } from "./crypto";
import { draftOrderWithOpenAI, ProviderResponseError, ProviderTimeoutError } from "./openai";

const UUID_V4 = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const SIGNATURE = /^v1=([a-f0-9]{64})$/;

/**
 * Human-reviewed AI order preparation endpoint.
 *
 * This endpoint is deliberately advisory: it never persists, sends or releases
 * an order. Drupal remains the only writer through the existing CommitmentManager.
 */
export async function orderDraft(request: Request, env: Env): Promise<Response> {
  const requestId = request.headers.get("X-BREBO-Request-Id") ?? crypto.randomUUID();
  if (request.method !== "POST") {
    return errorResponse(405, "method_not_allowed", "POST required.", requestId);
  }

  const path = "/v1/orders/draft";
  const bodyResult = await authenticatedJsonBody(request, env, path, requestId);
  if (bodyResult instanceof Response) return bodyResult;
  const body = bodyResult;

  let raw: unknown;
  try {
    raw = JSON.parse(body);
  } catch {
    return errorResponse(400, "invalid_request", "Request body is not valid JSON.", requestId);
  }

  const parsed = orderDraftRequestSchema.safeParse(raw);
  if (!parsed.success) {
    return errorResponse(400, "invalid_request", "Request does not match the order draft contract.", requestId);
  }

  const requestHash = await sha256Hex(requestId);
  const replay = env.REPLAY_GUARD.getByName(requestHash.slice(0, 2));
  const now = Math.floor(Date.now() / 1_000);
  if (!(await replay.useOnce(requestHash, now + numberSetting(env.REPLAY_TTL_SECONDS, 600), now))) {
    return errorResponse(409, "replayed_request", "Request identifier was already used.", requestId);
  }

  const usage = env.USAGE_GUARD.getByName("brebo-office");
  const maxOutputTokens = numberSetting(env.OPENAI_MAX_OUTPUT_TOKENS, 2_000);
  const usageDecision = await usage.reserve(
    now,
    numberSetting(env.RATE_WINDOW_SECONDS, 60),
    numberSetting(env.MAX_ANALYSES_PER_WINDOW, 30),
    new Date(now * 1_000).toISOString().slice(0, 7),
    Math.ceil(body.length / 4) + maxOutputTokens,
    numberSetting(env.MONTHLY_TOKEN_BUDGET, 2_000_000),
  );
  if (usageDecision === "rate_limited") {
    return errorResponse(429, "rate_limited", "Analysis rate limit reached.", requestId, {
      "Retry-After": String(numberSetting(env.RATE_WINDOW_SECONDS, 60)),
    });
  }
  if (usageDecision === "budget_exhausted") {
    return errorResponse(429, "budget_exhausted", "Monthly AI budget limit reached.", requestId);
  }

  try {
    const draft = await draftOrderWithOpenAI(parsed.data, env);
    return Response.json({
      status: "ok",
      mode: "production",
      stored: false,
      sent: false,
      request_id: requestId,
      draft,
    });
  } catch (error) {
    if (error instanceof ProviderTimeoutError) {
      return errorResponse(504, "provider_timeout", "AI provider timed out.", requestId);
    }
    if (error instanceof ProviderResponseError) {
      return errorResponse(502, "provider_error", "AI provider could not create a safe order draft.", requestId);
    }
    throw error;
  }
}

async function authenticatedJsonBody(request: Request, env: Env, path: string, requestId: string): Promise<string | Response> {
  const contentType = request.headers.get("Content-Type")?.split(";", 1)[0]?.trim().toLowerCase();
  if (contentType !== "application/json") {
    return errorResponse(415, "unsupported_media_type", "Content-Type must be application/json.", requestId);
  }
  const maxBytes = numberSetting(env.MAX_BODY_BYTES, 32_768);
  const contentLength = Number(request.headers.get("Content-Length"));
  if (Number.isFinite(contentLength) && contentLength > maxBytes) {
    return errorResponse(413, "payload_too_large", "Request body is too large.", requestId);
  }
  const body = await readBoundedBody(request, maxBytes);
  if (body === null) {
    return errorResponse(413, "payload_too_large", "Request body is too large.", requestId);
  }
  const auth = await authenticate(request, env, path, body, requestId);
  return auth ?? body;
}

async function authenticate(request: Request, env: Env, path: string, body: string, requestId: string): Promise<Response | null> {
  const timestampText = request.headers.get("X-BREBO-Timestamp") ?? "";
  const signatureText = request.headers.get("X-BREBO-Signature") ?? "";
  const signature = SIGNATURE.exec(signatureText)?.[1];
  const timestamp = Number(timestampText);
  if (!UUID_V4.test(requestId) || !Number.isInteger(timestamp) || timestamp <= 0 || !signature) {
    return errorResponse(401, "invalid_signature", "Request authentication failed.", safeRequestId(requestId));
  }
  const now = Math.floor(Date.now() / 1_000);
  if (Math.abs(now - timestamp) > numberSetting(env.MAX_CLOCK_SKEW_SECONDS, 300)) {
    return errorResponse(401, "invalid_signature", "Request authentication failed.", requestId);
  }
  const bodyHash = await sha256Hex(body);
  const canonical = [request.method.toUpperCase(), path, bodyHash, timestampText, requestId].join("\n");
  const expected = await hmacSha256Hex(env.BREBO_SHARED_SECRET, canonical);
  if (!(await fixedTimeEqual(signature, expected))) {
    return errorResponse(401, "invalid_signature", "Request authentication failed.", requestId);
  }
  return null;
}

async function readBoundedBody(request: Request, maxBytes: number): Promise<string | null> {
  if (!request.body) return "";
  const reader = request.body.getReader();
  const decoder = new TextDecoder("utf-8", { fatal: true });
  let received = 0;
  let value = "";
  try {
    while (true) {
      const chunk = await reader.read();
      if (chunk.done) break;
      received += chunk.value.byteLength;
      if (received > maxBytes) {
        await reader.cancel();
        return null;
      }
      value += decoder.decode(chunk.value, { stream: true });
    }
    value += decoder.decode();
    return value;
  } catch {
    return "";
  }
}

function errorResponse(status: number, code: string, message: string, requestId: string, headers?: HeadersInit): Response {
  return Response.json(
    { status: "error", request_id: safeRequestId(requestId), error: { code, message } },
    headers ? { status, headers } : { status },
  );
}

function safeRequestId(value: string): string {
  return UUID_V4.test(value) ? value : "00000000-0000-4000-8000-000000000000";
}

function numberSetting(value: string, fallback: number): number {
  const parsed = Number.parseInt(value, 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}
