import core from "./index";
import { publicProjects } from "./public-projects";

const PUBLIC_PROJECT_DETAIL = /^\/v1\/public\/projects\/([^/]+)$/;

export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    const url = new URL(request.url);

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
} satisfies ExportedHandler<Env>;

export { ReplayGuard, UsageGuard, SalesInvoiceDispatchGuard } from "./index";
