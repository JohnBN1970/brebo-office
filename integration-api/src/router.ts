import core from "./index";
import { calcPublication } from "./calc-publication-route";
import { documentExtraction, type ExtractionEnv } from "./document-extraction";
import { orderDraft } from "./order-draft";
import { publicProjects } from "./public-projects";
import { publicEuropakozijnIntake } from "./public-intake";

const PUBLIC_PROJECT_DETAIL = /^\/v1\/public\/projects\/([^/]+)$/;

export default {
  async fetch(request: Request, env: ExtractionEnv): Promise<Response> {
    const url = new URL(request.url);

    if (/^\/api\/workbench\/v2\/calculations\/[1-9][0-9]*\/calc-results$/.test(url.pathname)) {
      try {
        return await calcPublication(request, env);
      } catch (error) {
        console.error(JSON.stringify({ event: "calc_publication_failed", category: error instanceof Error ? error.constructor.name : "UnknownError" }));
        return Response.json({ ok: false, error: { code: "internal_error" } }, { status: 500, headers: { "Cache-Control": "no-store" } });
      }
    }

    if (url.pathname === "/v1/internal/document-extraction") {
      return documentExtraction(request, env);
    }

    if (url.pathname === "/v1/orders/draft") {
      return orderDraft(request, env);
    }

    if (url.pathname === "/v1/intake/europakozijn") {
      return publicEuropakozijnIntake(request, env);
    }

    if (url.pathname === "/v1/public/projects") {
      return publicProjects(request, env);
    }

    const detail = PUBLIC_PROJECT_DETAIL.exec(url.pathname);
    const encodedPublicId = detail?.[1];
    if (encodedPublicId !== undefined) {
      return publicProjects(request, env, decodeURIComponent(encodedPublicId));
    }

    return core.fetch(request, env);
  },
} satisfies ExportedHandler<ExtractionEnv>;

export { ReplayGuard, UsageGuard, SalesInvoiceDispatchGuard } from "./index";

export { CalcPublicationStore } from "./calc-publication-store";
\nexport { CalcAccessRegistry } from "./calc-access-registry";\n