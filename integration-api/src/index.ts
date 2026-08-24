import { analyzeRequestSchema } from "./contracts";
import { fixedTimeEqual, hmacSha256Hex, sha256Hex } from "./crypto";
import { checkMoneybirdConnection, createAndSendSalesInvoice, MoneybirdResponseError, SalesInvoiceDispatch } from "./moneybird";
import { analyzeWithOpenAI, ProviderResponseError, ProviderTimeoutError } from "./openai";
export { ReplayGuard } from "./replay-guard";
export { UsageGuard } from "./usage-guard";
export { SalesInvoiceDispatchGuard } from "./sales-invoice-dispatch-guard";

const UUID_V4 = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const SIGNATURE = /^v1=([a-f0-9]{64})$/;

export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    const startedAt = Date.now();
    const url = new URL(request.url);
    const requestId = request.headers.get("X-BREBO-Request-Id") ?? crypto.randomUUID();
    let response: Response;

    try {
      if (url.pathname === "/health/status" && request.method === "GET") {
        response = await authenticatedHealth(request, env, url.pathname, requestId);
      } else if (url.pathname === "/v1/accounting/connection" && request.method === "GET") {
        response = await accountingConnection(request, env, url.pathname, requestId);
      } else if (url.pathname === "/v1/communications/analyze" && request.method === "POST") {
        response = await analyze(request, env, url.pathname, requestId);
      } else if (url.pathname === "/v1/accounting/sales-invoices" && request.method === "POST") {
        response = await dispatchSalesInvoice(request, env, url.pathname, requestId);
      } else {
        response = errorResponse(404, "not_found", "Endpoint not found.", requestId);
      }
    } catch (error) {
      const category = error instanceof Error ? error.constructor.name : "UnknownError";
      console.error(JSON.stringify({ event: "request_failed", endpoint: url.pathname, category }));
      response = errorResponse(500, "internal_error", "Internal service error.", requestId);
    }

    console.log(JSON.stringify({ event: "request_completed", request_hash: await sha256Hex(requestId), endpoint: url.pathname, status: response.status, duration_ms: Date.now() - startedAt, worker_version: env.WORKER_VERSION }));
    return response;
  },
} satisfies ExportedHandler<Env>;

async function authenticatedHealth(request: Request, env: Env, path: string, requestId: string): Promise<Response> {
  const auth = await authenticate(request, env, path, "", requestId);
  if (auth) return auth;
  return Response.json({ status: "healthy", api_version: env.API_VERSION, worker_version: env.WORKER_VERSION });
}

async function accountingConnection(request: Request, env: Env, path: string, requestId: string): Promise<Response> {
  const auth = await authenticate(request, env, path, "", requestId);
  if (auth) return auth;
  if (!env.MONEYBIRD_ACCESS_TOKEN || !env.MONEYBIRD_ADMINISTRATION_ID || env.MONEYBIRD_ADMINISTRATION_ID === "REPLACE_IN_DEPLOYMENT") {
    return errorResponse(503, "accounting_not_configured", "Accounting provider configuration is incomplete.", requestId);
  }
  try {
    const administration = await checkMoneybirdConnection(env);
    return Response.json({ status: "ok", request_id: requestId, provider: "moneybird", connection: "healthy", administration });
  } catch (error) {
    if (error instanceof MoneybirdResponseError) {
      console.error(JSON.stringify({ event: "moneybird_connection_failed", request_hash: await sha256Hex(requestId), provider_status: error.status }));
      return errorResponse(502, "accounting_connection_failed", "Accounting provider connection check failed.", requestId);
    }
    throw error;
  }
}

async function dispatchSalesInvoice(request: Request, env: Env, path: string, requestId: string): Promise<Response> {
  const bodyResult = await authenticatedJsonBody(request, env, path, requestId);
  if (bodyResult instanceof Response) return bodyResult;
  let raw: unknown;
  try { raw = JSON.parse(bodyResult); } catch { return errorResponse(400, "invalid_request", "Request body is not valid JSON.", requestId); }
  if (!raw || typeof raw !== "object") return errorResponse(400, "invalid_request", "Request does not match the contract.", requestId);
  const value = raw as Record<string, unknown>;
  if (typeof value.idempotency_key !== "string" || value.idempotency_key.length < 16 || !value.sales_invoice || typeof value.sales_invoice !== "object" || Array.isArray(value.sales_invoice) || (value.sending !== undefined && (!value.sending || typeof value.sending !== "object" || Array.isArray(value.sending)))) {
    return errorResponse(400, "invalid_request", "Request does not match the contract.", requestId);
  }

  if (!env.MONEYBIRD_ACCESS_TOKEN || !env.MONEYBIRD_ADMINISTRATION_ID || env.MONEYBIRD_ADMINISTRATION_ID === "REPLACE_IN_DEPLOYMENT") {
    return errorResponse(503, "accounting_not_configured", "Accounting provider configuration is incomplete.", requestId);
  }

  const commandHash = await sha256Hex(`sales-invoice:${value.idempotency_key}`);
  const guard = env.SALES_INVOICE_DISPATCH_GUARD.getByName(commandHash.slice(0, 2));
  const now = Math.floor(Date.now() / 1_000);
  const started = await guard.begin(commandHash, now + numberSetting(env.OUTBOUND_IDEMPOTENCY_TTL_SECONDS, 2_592_000), now);
  if (!started.accepted) {
    if (started.record.state === "completed" && started.record.response_json) {
      try {
        return Response.json(JSON.parse(started.record.response_json), { status: 200, headers: { "X-BREBO-Idempotent-Replay": "true" } });
      } catch {
        return errorResponse(500, "dispatch_state_error", "Stored accounting result could not be read.", requestId);
      }
    }
    return errorResponse(409, "reconciliation_required", "Sales invoice command is already in progress or requires reconciliation before retry.", requestId);
  }

  try {
    const invoice = await createAndSendSalesInvoice(value as unknown as SalesInvoiceDispatch, env);
    const responsePayload = { status: "ok", request_id: requestId, provider: "moneybird", sales_invoice: invoice };
    await guard.complete(commandHash, JSON.stringify(responsePayload), Math.floor(Date.now() / 1_000));
    return Response.json(responsePayload);
  } catch (error) {
    await guard.requireReconciliation(commandHash, Math.floor(Date.now() / 1_000));
    if (error instanceof MoneybirdResponseError) {
      console.error(JSON.stringify({ event: "moneybird_sales_invoice_failed", request_hash: await sha256Hex(requestId), provider_status: error.status, reconciliation_required: true }));
      return errorResponse(502, "reconciliation_required", "Accounting provider outcome requires reconciliation before retry.", requestId);
    }
    console.error(JSON.stringify({ event: "moneybird_sales_invoice_uncertain", request_hash: await sha256Hex(requestId), reconciliation_required: true }));
    return errorResponse(502, "reconciliation_required", "Accounting provider outcome is uncertain and requires reconciliation before retry.", requestId);
  }
}

async function analyze(request: Request, env: Env, path: string, requestId: string): Promise<Response> {
  const bodyResult = await authenticatedJsonBody(request, env, path, requestId);
  if (bodyResult instanceof Response) return bodyResult;
  const body = bodyResult;
  let raw: unknown;
  try { raw = JSON.parse(body); } catch { return errorResponse(400, "invalid_request", "Request body is not valid JSON.", requestId); }
  const parsed = analyzeRequestSchema.safeParse(raw);
  if (!parsed.success) {
    const unsafeTestData = parsed.error.issues.some((issue) => issue.path[0] === "contains_real_data");
    return errorResponse(unsafeTestData ? 422 : 400, unsafeTestData ? "unsafe_or_unprocessable" : "invalid_request", unsafeTestData ? "Test mode cannot process real data." : "Request does not match the contract.", requestId);
  }
  if (parsed.data.communication.message.length > numberSetting(env.MAX_INPUT_CHARS, 12_000)) return errorResponse(400, "invalid_request", "Message exceeds the configured limit.", requestId);
  const requestHash = await sha256Hex(requestId);
  const replay = env.REPLAY_GUARD.getByName(requestHash.slice(0, 2));
  const now = Math.floor(Date.now() / 1_000);
  if (!(await replay.useOnce(requestHash, now + numberSetting(env.REPLAY_TTL_SECONDS, 600), now))) return errorResponse(409, "replayed_request", "Request identifier was already used.", requestId);
  const usage = env.USAGE_GUARD.getByName("brebo-office");
  const maxOutputTokens = numberSetting(env.OPENAI_MAX_OUTPUT_TOKENS, 2_000);
  const usageDecision = await usage.reserve(now, numberSetting(env.RATE_WINDOW_SECONDS, 60), numberSetting(env.MAX_ANALYSES_PER_WINDOW, 30), new Date(now * 1_000).toISOString().slice(0, 7), Math.ceil(body.length / 4) + maxOutputTokens, numberSetting(env.MONTHLY_TOKEN_BUDGET, 2_000_000));
  if (usageDecision === "rate_limited") return errorResponse(429, "rate_limited", "Analysis rate limit reached.", requestId, { "Retry-After": String(numberSetting(env.RATE_WINDOW_SECONDS, 60)) });
  if (usageDecision === "budget_exhausted") return errorResponse(429, "budget_exhausted", "Monthly AI budget limit reached.", requestId);
  try {
    const analysis = await analyzeWithOpenAI(parsed.data, env);
    return Response.json({ status: "ok", mode: parsed.data.test_mode ? "test" : "production", stored: false, sent: false, request_id: requestId, analysis });
  } catch (error) {
    if (error instanceof ProviderTimeoutError) return errorResponse(504, "provider_timeout", "AI provider timed out.", requestId);
    if (error instanceof ProviderResponseError) return errorResponse(502, "provider_error", "AI provider could not complete the analysis.", requestId);
    throw error;
  }
}

async function authenticatedJsonBody(request: Request, env: Env, path: string, requestId: string): Promise<string | Response> {
  const contentType = request.headers.get("Content-Type")?.split(";", 1)[0]?.trim().toLowerCase();
  if (contentType !== "application/json") return errorResponse(415, "unsupported_media_type", "Content-Type must be application/json.", requestId);
  const maxBytes = numberSetting(env.MAX_BODY_BYTES, 32_768);
  const contentLength = Number(request.headers.get("Content-Length"));
  if (Number.isFinite(contentLength) && contentLength > maxBytes) return errorResponse(413, "payload_too_large", "Request body is too large.", requestId);
  const body = await readBoundedBody(request, maxBytes);
  if (body === null) return errorResponse(413, "payload_too_large", "Request body is too large.", requestId);
  const auth = await authenticate(request, env, path, body, requestId);
  return auth ?? body;
}

async function authenticate(request: Request, env: Env, path: string, body: string, requestId: string): Promise<Response | null> {
  const timestampText = request.headers.get("X-BREBO-Timestamp") ?? "";
  const signatureText = request.headers.get("X-BREBO-Signature") ?? "";
  const signature = SIGNATURE.exec(signatureText)?.[1];
  const timestamp = Number(timestampText);
  if (!UUID_V4.test(requestId) || !Number.isInteger(timestamp) || timestamp <= 0 || !signature) return errorResponse(401, "invalid_signature", "Request authentication failed.", safeRequestId(requestId));
  const now = Math.floor(Date.now() / 1_000);
  if (Math.abs(now - timestamp) > numberSetting(env.MAX_CLOCK_SKEW_SECONDS, 300)) return errorResponse(401, "invalid_signature", "Request authentication failed.", requestId);
  const bodyHash = await sha256Hex(body);
  const canonical = [request.method.toUpperCase(), path, bodyHash, timestampText, requestId].join("\n");
  const expected = await hmacSha256Hex(env.BREBO_SHARED_SECRET, canonical);
  if (!(await fixedTimeEqual(signature, expected))) return errorResponse(401, "invalid_signature", "Request authentication failed.", requestId);
  return null;
}

async function readBoundedBody(request: Request, maxBytes: number): Promise<string | null> {
  if (!request.body) return "";
  const reader = request.body.getReader(); const decoder = new TextDecoder("utf-8", { fatal: true }); let received = 0; let value = "";
  try { while (true) { const chunk = await reader.read(); if (chunk.done) break; received += chunk.value.byteLength; if (received > maxBytes) { await reader.cancel(); return null; } value += decoder.decode(chunk.value, { stream: true }); } value += decoder.decode(); return value; } catch { return ""; }
}

function errorResponse(status: number, code: string, message: string, requestId: string, headers?: HeadersInit): Response { return Response.json({ status: "error", request_id: safeRequestId(requestId), error: { code, message } }, headers ? { status, headers } : { status }); }
function safeRequestId(value: string): string { return UUID_V4.test(value) ? value : "00000000-0000-4000-8000-000000000000"; }
function numberSetting(value: string, fallback: number): number { const parsed = Number.parseInt(value, 10); return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback; }
