import { defineConfig } from "vitest/config";

// Pure Moneybird HTTP contract tests run in Node without launching Wrangler
// or connecting to a Cloudflare remote worker.
export default defineConfig({
  test: {
    environment: "node",
    include: ["test/moneybird-bank-mutations.test.ts"],
  },
});
