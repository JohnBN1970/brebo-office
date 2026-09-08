const SUPPORTED_MIME_TYPES = new Set([
  "application/pdf",
  "image/jpeg",
  "image/png",
  "image/webp",
]);

const MAX_DOCUMENT_BYTES = 15 * 1024 * 1024;
const MAX_MULTIPART_OVERHEAD_BYTES = 64 * 1024;
const MAX_REQUEST_BYTES = MAX_DOCUMENT_BYTES + MAX_MULTIPART_OVERHEAD_BYTES;

type ExtractionEnv = Env & {
  AI: {
    toMarkdown(
      input: Array<{ name: string; blob: Blob }>,
      options?: Record<string, unknown>,
    ): Promise<unknown>;
  };
  DOCUMENT_EXTRACTION_TOKEN?: string;
  EXTRACTION_RATE_WINDOW_SECONDS?: string;
  MAX_EXTRACTIONS_PER_WINDOW?: string;
  MONTHLY_EXTRACTION_BUDGET?: string;
};

type ConversionResult = {
  name?: string;
  mimeType?: string;
  format?: string;
  data?: string;
  error?: string;
};

function json(body: unknown, status = 200, headers?: HeadersInit): Response {
  return Response.json(body, {
    status,
    headers: {
      "Cache-Control": "no-store",
      "X-Content-Type-Options": "nosniff",
      ...headers,
    },
  });
}

function authorized(request: Request, env: ExtractionEnv): boolean {
  const token = env.DOCUMENT_EXTRACTION_TOKEN?.trim() ?? "";
  if (token === "") return false;
  return request.headers.get("Authorization") === `Bearer ${token}`;
}

function boundedContentLength(request: Request): number | null {
  const raw = request.headers.get("Content-Length");
  if (raw === null || !/^\d+$/.test(raw)) return null;
  const value = Number(raw);
  return Number.isSafeInteger(value) ? value : null;
}

function positiveIntegerSetting(value: string | undefined, fallback: number): number {
  const parsed = Number.parseInt(value ?? "", 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

export async function documentExtraction(request: Request, env: ExtractionEnv): Promise<Response> {
  if (request.method !== "POST") {
    return json({ status: "method_not_allowed" }, 405);
  }
  if (!authorized(request, env)) {
    return json({ status: "unauthorized" }, 401);
  }
  if (request.headers.get("X-BREBO-Extraction-Contract") !== "v1") {
    return json({ status: "unsupported_contract" }, 400);
  }

  const contentLength = boundedContentLength(request);
  if (contentLength === null) {
    return json({ status: "content_length_required" }, 411);
  }
  if (contentLength === 0) {
    return json({ status: "document_required" }, 400);
  }
  if (contentLength > MAX_REQUEST_BYTES) {
    return json({ status: "document_too_large" }, 413);
  }

  const now = Math.floor(Date.now() / 1_000);
  const month = new Date(now * 1_000).toISOString().slice(0, 7);
  const rateWindowSeconds = positiveIntegerSetting(env.EXTRACTION_RATE_WINDOW_SECONDS, 60);
  const requestUsage = env.USAGE_GUARD.getByName("document-extraction-rate");
  const requestDecision = await requestUsage.reserve(
    now,
    rateWindowSeconds,
    positiveIntegerSetting(env.MAX_EXTRACTIONS_PER_WINDOW, 10),
    month,
    0,
    Number.MAX_SAFE_INTEGER,
  );
  if (requestDecision === "rate_limited") {
    return json({ status: "rate_limited" }, 429, { "Retry-After": String(rateWindowSeconds) });
  }

  let form: FormData;
  try {
    form = await request.formData();
  } catch {
    return json({ status: "invalid_multipart" }, 400);
  }

  if ([...form.keys()].some((key) => key !== "document")) {
    return json({ status: "unexpected_multipart_field" }, 400);
  }
  const documents = form.getAll("document");
  if (documents.length !== 1 || !(documents[0] instanceof File)) {
    return json({ status: "document_required" }, 400);
  }
  const document = documents[0];
  if (document.size === 0 || document.size > MAX_DOCUMENT_BYTES) {
    return json({ status: document.size === 0 ? "document_empty" : "document_too_large" }, document.size === 0 ? 400 : 413);
  }

  const mimeType = document.type.toLowerCase();
  if (!SUPPORTED_MIME_TYPES.has(mimeType)) {
    return json({ status: "unsupported_mime_type" }, 415);
  }

  const budgetUsage = env.USAGE_GUARD.getByName("document-extraction-budget");
  const budgetDecision = await budgetUsage.reserve(
    now,
    rateWindowSeconds,
    Number.MAX_SAFE_INTEGER,
    month,
    1,
    positiveIntegerSetting(env.MONTHLY_EXTRACTION_BUDGET, 5_000),
  );
  if (budgetDecision === "budget_exhausted") {
    return json({ status: "budget_exhausted" }, 429);
  }

  let converted: unknown;
  try {
    converted = await env.AI.toMarkdown(
      [{ name: document.name || "document", blob: document }],
      {
        conversionOptions: {
          output: { format: "text" },
          pdf: { metadata: false },
        },
      },
    );
  } catch {
    return json({ status: "extraction_failed" }, 502);
  }

  const first: ConversionResult | undefined = Array.isArray(converted)
    ? (converted[0] as ConversionResult | undefined)
    : (converted as ConversionResult | undefined);
  if (!first || typeof first !== "object") {
    return json({ status: "invalid_extraction_result" }, 502);
  }
  if (first.format === "error" || first.error) {
    return json({ status: "extraction_failed" }, 422);
  }
  if (first.format !== "text" || typeof first.data !== "string") {
    return json({ status: "invalid_extraction_result" }, 502);
  }

  const text = first.data.trim();
  return json({
    status: text === "" ? "no_text" : "extracted",
    text,
    extractor: "cloudflare_workers_ai_tomarkdown_text_v1",
    confidence: text === "" ? 0 : 0.85,
  });
}
