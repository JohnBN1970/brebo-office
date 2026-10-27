import { describe, expect, it } from "vitest";
import { documentExtraction, verifiedPositionBlocks } from "../src/document-extraction";

describe("document extraction position blocks", () => {
  it("preserves real normalized positions", () => {
    expect(verifiedPositionBlocks([{ page: 1, text: "PVC hoek", x: 0.12, y: 0.32 }]))
      .toEqual([{ page: 1, text: "PVC hoek", x: 0.12, y: 0.32 }]);
  });
  it("does not invent coordinates when text-only provider returns nothing", () => {
    expect(verifiedPositionBlocks(undefined)).toEqual([]);
  });
  it("rejects out-of-range and malformed blocks as a whole", () => {
    expect(verifiedPositionBlocks([{ page: 1, text: "x", x: 1.2, y: 0.3 }])).toEqual([]);
    expect(verifiedPositionBlocks([{ page: 1, text: "x", x: NaN, y: 0.3 }])).toEqual([]);
    expect(verifiedPositionBlocks([{ page: 1, text: "x", x: 0.2, y: 0.3 }, { page: 0, text: "bad", x: 0, y: 0 }])).toEqual([]);
  });
});

describe("document extraction optional PDF positions", () => {
  const request = () => {
    const form = new FormData();
    form.set("document", new File([new Uint8Array([37, 80, 68, 70])], "test.pdf", { type: "application/pdf" }));
    const body = new Request("https://example.test", { method: "POST", body: form });
    return new Request(body, { headers: {
      "Content-Type": body.headers.get("Content-Type") ?? "",
      Authorization: "Bearer secret",
      "X-BREBO-Extraction-Contract": "v1",
      "Content-Length": "1024",
    } });
  };
  const env = (getDocument?: (options: Record<string, unknown>) => { promise: Promise<unknown> }) => ({
    DOCUMENT_EXTRACTION_TOKEN: "secret",
    USAGE_GUARD: { getByName: () => ({ reserve: async () => "allowed" }) },
    AI: { toMarkdown: async () => [{ format: "text", data: "PVC40101 96,00 2,23 214,08" }] },
    ...(getDocument ? { PDF_GET_DOCUMENT: getDocument } : {}),
  });
  it("adds real embedded positions when a parser is available", async () => {
    const parser = () => ({ promise: Promise.resolve({ numPages: 1, getPage: async () => ({
      view: [0, 0, 595, 842],
      getTextContent: async () => ({ items: [{ str: "PVC40101", transform: [1, 0, 0, 1, 51, 515] }] }),
    }) }) });
    const response = await documentExtraction(request(), env(parser) as never);
    expect(response.status).toBe(200);
    const result = await response.json() as { blocks?: unknown[]; text: string };
    expect(result.blocks).toEqual([{ page: 1, text: "PVC40101", x: 51 / 595, y: 327 / 842 }]);
    expect(result.text).toContain("PVC40101");
  });
  it("keeps text extraction when optional parser fails", async () => {
    const response = await documentExtraction(request(), env(() => { throw new Error("unavailable"); }) as never);
    const result = await response.json() as { blocks?: unknown[]; text: string };
    expect(response.status).toBe(200);
    expect(result.blocks).toBeUndefined();
    expect(result.text).toContain("PVC40101");
  });
});
