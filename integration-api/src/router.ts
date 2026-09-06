import core from "./index";
import { publicProjects } from "./public-projects";

const PUBLIC_PROJECT_DETAIL = /^\/v1\/public\/projects\/([^/]+)$/;

export default {
  async fetch(request: Request, env: Env, ctx: ExecutionContext): Promise<Response> {
    const url = new URL(request.url);

    if (url.pathname === "/v1/public/projects") {
      return publicProjects(request, env);
    }

    const detail = PUBLIC_PROJECT_DETAIL.exec(url.pathname);
    if (detail) {
      return publicProjects(request, env, decodeURIComponent(detail[1]));
    }

    return core.fetch(request, env, ctx);
  },
} satisfies ExportedHandler<Env>;

export { ReplayGuard, UsageGuard, SalesInvoiceDispatchGuard } from "./index";
