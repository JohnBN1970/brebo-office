import { hmacSha256Hex, sha256Hex } from "./crypto";

const PUBLIC_ID = /^[A-Za-z0-9._-]+$/;

export async function publicProjects(request: Request, env: Env, publicId?: string): Promise<Response> {
  if (request.method !== "GET") {
    return Response.json({ status: "error", error: { code: "method_not_allowed" } }, { status: 405, headers: { Allow: "GET" } });
  }
  if (publicId !== undefined && !PUBLIC_ID.test(publicId)) {
    return Response.json({ status: "error", error: { code: "not_found" } }, { status: 404 });
  }

  const sourcePath = publicId === undefined
    ? "/brebo-internal/public-projects"
    : `/brebo-internal/public-projects/${encodeURIComponent(publicId)}`;

  let source: Response;
  try {
    source = await fetchOfficeProjection(env, sourcePath);
  } catch (error) {
    console.error(JSON.stringify({ event: "public_project_source_transport_failed", category: error instanceof Error ? error.constructor.name : "UnknownError" }));
    return sourceUnavailable();
  }

  if (source.status === 404) {
    return Response.json({ status: "error", error: { code: "not_found" } }, { status: 404, headers: publicHeaders(60) });
  }
  if (!source.ok) {
    console.error(JSON.stringify({ event: "public_project_source_failed", source_status: source.status }));
    return sourceUnavailable();
  }

  try {
    const payload = await source.json();
    return Response.json(payload, { status: 200, headers: publicHeaders(300) });
  } catch (error) {
    console.error(JSON.stringify({ event: "public_project_source_invalid_json", category: error instanceof Error ? error.constructor.name : "UnknownError" }));
    return sourceUnavailable();
  }
}

async function fetchOfficeProjection(env: Env, path: string): Promise<Response> {
  const baseUrl = env.OFFICE_PUBLICATION_BASE_URL?.replace(/\/$/, "");
  if (!baseUrl || !env.BREBO_SHARED_SECRET) {
    return new Response(null, { status: 503 });
  }

  const timestamp = Math.floor(Date.now() / 1_000).toString();
  const requestId = crypto.randomUUID();
  const bodyHash = await sha256Hex("");
  const canonical = ["GET", path, bodyHash, timestamp, requestId].join("\n");
  const signature = await hmacSha256Hex(env.BREBO_SHARED_SECRET, canonical);

  return fetch(`${baseUrl}${path}`, {
    method: "GET",
    headers: {
      "Accept": "application/json",
      "X-BREBO-Request-Id": requestId,
      "X-BREBO-Timestamp": timestamp,
      "X-BREBO-Signature": `v1=${signature}`,
    },
  });
}

function sourceUnavailable(): Response {
  return Response.json(
    { status: "error", error: { code: "project_source_unavailable" } },
    { status: 502, headers: publicHeaders(0) },
  );
}

function publicHeaders(maxAge: number): HeadersInit {
  return {
    "Cache-Control": maxAge > 0 ? `public, max-age=${maxAge}, must-revalidate` : "no-store",
    "Content-Security-Policy": "default-src 'none'; frame-ancestors 'none'",
    "X-Content-Type-Options": "nosniff",
  };
}
