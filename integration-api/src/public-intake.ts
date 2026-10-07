import { hmacSha256Hex, sha256Hex } from "./crypto";

const UUID = /^[0-9a-f-]{36}$/i;

export async function publicEuropakozijnIntake(request: Request, env: Env): Promise<Response> {
  if (request.method !== "POST") {
    return Response.json({ status: "error", error: { code: "method_not_allowed" } }, { status: 405, headers: { Allow: "POST" } });
  }

  const raw = await request.text();
  if (raw.length === 0 || raw.length > 64_000) {
    return error(400, "invalid_request");
  }

  let payload: unknown;
  try { payload = JSON.parse(raw); } catch { return error(400, "invalid_json"); }
  if (!payload || typeof payload !== "object" || Array.isArray(payload)) return error(400, "invalid_request");
  const input = payload as Record<string, unknown>;
  const requestId = typeof input.request_id === "string" ? input.request_id.trim() : "";
  const observed = input.observed;
  if (!UUID.test(requestId) || !observed || typeof observed !== "object" || Array.isArray(observed)) return error(422, "invalid_request");

  const observedRecord = observed as Record<string, unknown>;
  if (!observedRecord.building || !Array.isArray(observedRecord.rooms) || observedRecord.rooms.length === 0 || !Array.isArray(observedRecord.frames) || observedRecord.frames.length === 0) {
    return error(422, "incomplete_request");
  }

  const now = Math.floor(Date.now() / 1_000);
  const usage = env.USAGE_GUARD.getByName("public-europakozijn-intake");
  const decision = await usage.reserve(now, 60, 30, new Date(now * 1_000).toISOString().slice(0, 7), 1, 1000000);
  if (decision === "rate_limited") return error(429, "rate_limited", { "Retry-After": "60" });

  const baseUrl = env.OFFICE_PUBLICATION_BASE_URL?.replace(/\/$/, "");
  if (!baseUrl || !env.BREBO_SHARED_SECRET) return error(503, "office_unavailable");

  const officePath = "/brebo-internal/intake/europakozijn";
  const body = JSON.stringify(input);
  const timestamp = now.toString();
  const canonical = ["POST", officePath, await sha256Hex(body), timestamp, requestId].join("\n");
  const signature = await hmacSha256Hex(env.BREBO_SHARED_SECRET, canonical);

  let response: Response;
  try {
    response = await fetch(`${baseUrl}${officePath}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-BREBO-Timestamp": timestamp,
        "X-BREBO-Request-Id": requestId,
        "X-BREBO-Signature": `v1=${signature}`,
      },
      body,
    });
  } catch {
    return error(502, "office_unavailable");
  }

  const responseBody = await response.text();
  if (!response.ok) return error(502, "office_rejected_request");

  try {
    const decoded = JSON.parse(responseBody);
    return Response.json(decoded, { status: 202, headers: publicHeaders() });
  } catch {
    return error(502, "office_invalid_response");
  }
}

function error(status: number, code: string, headers: HeadersInit = {}): Response {
  return Response.json({ status: "error", error: { code } }, { status, headers: { ...publicHeaders(), ...headers } });
}

function publicHeaders(): HeadersInit {
  return {
    "Cache-Control": "private, no-store",
    "X-Content-Type-Options": "nosniff",
    "Access-Control-Allow-Origin": "https://brebobv.nl",
    "Vary": "Origin",
  };
}
