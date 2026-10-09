import { fixedTimeEqual, hmacSha256Hex, sha256Hex } from "./crypto";
import { parseCalcPublication } from "./calc-publication";

const PATH = /^\/api\/workbench\/v2\/calculations\/([1-9][0-9]*)\/calc-results$/;
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const SIGNATURE = /^v1=([a-f0-9]{64})$/;

/** HMAC-authenticated standalone Office publication endpoint. */
export async function calcPublication(request: Request, env: Env): Promise<Response> {
  const path = new URL(request.url).pathname;
  const match = PATH.exec(path);
  if (!match) return error(404, "not_found");
  if (request.method !== "POST" && request.method !== "GET") return error(405, "method_not_allowed");
  if (request.method === "POST" && request.headers.get("Content-Type")?.split(";")[0]?.trim().toLowerCase() !== "application/json") return error(415, "unsupported_media_type");
  const maxBytes = 32768;
  const declaredLength = request.headers.get("Content-Length");
  if (declaredLength !== null && Number(declaredLength) > maxBytes) return error(413, "payload_too_large");
  const bodyResult = request.method === "GET" ? { body: "" } : await readBoundedBody(request, maxBytes);
  if (bodyResult === null) return error(413, "payload_too_large");
  const body = bodyResult.body;
  const requestId = request.headers.get("X-BREBO-Request-Id") ?? "";
  const timestampText = request.headers.get("X-BREBO-Timestamp") ?? "";
  const signature = SIGNATURE.exec(request.headers.get("X-BREBO-Signature") ?? "")?.[1];
  const timestamp = Number(timestampText);
  if (!UUID.test(requestId) || !signature || !Number.isSafeInteger(timestamp) || timestamp <= 0 || Math.abs(Math.floor(Date.now() / 1000) - timestamp) > 300) return error(401, "invalid_signature");
  const actorHeader = request.method === "GET" ? request.headers.get("X-BREBO-Actor-Id") ?? "" : "";
  const canonical = [request.method, path, await sha256Hex(body), timestampText, requestId, ...(request.method === "GET" ? [actorHeader] : [])].join("\n");
  if (!env.BREBO_SHARED_SECRET || env.BREBO_SHARED_SECRET.trim().length < 32) return error(503, "publication_auth_not_configured");
  const expected = await hmacSha256Hex(env.BREBO_SHARED_SECRET, canonical);
  if (!(await fixedTimeEqual(signature, expected))) return error(401, "invalid_signature");

  const calculationId = Number(match[1]);
  if (!Number.isSafeInteger(calculationId)) return error(400, "invalid_calculation");
  const access = (env as Env & { CALC_PUBLICATION_ACCESS?: { canRead(calculationId: number, actorId: number): Promise<boolean>; canPublish(calculationId: number, actorId: number): Promise<boolean> } }).CALC_PUBLICATION_ACCESS;
  if (request.method === "GET") {
    const actorId = Number(actorHeader);
    if (!Number.isSafeInteger(actorId) || actorId <= 0) return error(400, "invalid_actor");
    if (!access) return error(503, "publication_access_not_configured");
    if (!(await access.canRead(calculationId, actorId))) return error(403, "access_denied");
    const latest = await env.CALC_PUBLICATION_STORE.getByName(String(calculationId)).latest() as { snapshot_id: number; content_hash: string; published_by: number; published_at: number; payload: unknown } | null;
    if (!latest) return error(404, "snapshot_not_found");
    const payload = latest.payload as { office_version: string; calc_version: string; commercial_summary: unknown };
    return Response.json({ ok: true, calculation_id: calculationId, snapshot_id: latest.snapshot_id, content_hash: latest.content_hash, published_by: latest.published_by, published_at: latest.published_at, office_version: payload.office_version, calc_version: payload.calc_version, commercial_summary: payload.commercial_summary }, { headers: { "Cache-Control": "no-store" } });
  }

  let raw: unknown;
  try { raw = JSON.parse(body); } catch { return error(400, "invalid_json"); }
  let publication: ReturnType<typeof parseCalcPublication>;
  try { publication = parseCalcPublication(raw); } catch { return error(400, "invalid_publication"); }
  const actorId = raw && typeof raw === "object" && "actor_id" in raw ? Number(raw.actor_id) : NaN;
  if (!Number.isSafeInteger(actorId) || actorId <= 0) return error(400, "invalid_actor");
  if (!access) return error(503, "publication_access_not_configured");
  if (!(await access.canPublish(calculationId, actorId))) return error(403, "access_denied");
  const replayHash = await sha256Hex(requestId);
  const replay = env.REPLAY_GUARD.getByName(replayHash.slice(0, 2));
  const now = Math.floor(Date.now() / 1000);
  if (!(await replay.useOnce(replayHash, now + 600, now))) return error(409, "replayed_request");

  const canonicalPayload = { contract: "brebo-calc-commercial-summary-v1", calculation_id: calculationId, ...publication };
  const json = JSON.stringify(canonicalPayload);
  const contentHash = await sha256Hex(json);
  const store = env.CALC_PUBLICATION_STORE.getByName(String(calculationId));
  const result = await store.publish(contentHash, publication.office_version, publication.calc_version, json, actorId);
  return Response.json({ ok: true, ...result }, { status: result.created ? 201 : 200, headers: { "Cache-Control": "no-store" } });
}

function error(status: number, code: string): Response {
  return Response.json({ ok: false, error: { code } }, { status, headers: { "Cache-Control": "no-store" } });
}

/** Enforce the payload limit while streaming, before buffering untrusted input. */
async function readBoundedBody(request: Request, maxBytes: number): Promise<{ body: string } | null> {
  if (!request.body) return { body: "" };
  const reader = request.body.getReader();
  const chunks: Uint8Array[] = [];
  let length = 0;
  try {
    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      length += value.byteLength;
      if (length > maxBytes) {
        await reader.cancel();
        return null;
      }
      chunks.push(value);
    }
  } finally {
    reader.releaseLock();
  }
  const bytes = new Uint8Array(length);
  let offset = 0;
  for (const chunk of chunks) { bytes.set(chunk, offset); offset += chunk.byteLength; }
  return { body: new TextDecoder().decode(bytes) };
}
