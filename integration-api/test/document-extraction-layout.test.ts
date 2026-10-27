import { describe, expect, it } from "vitest";
import { verifiedPositionBlocks } from "../src/document-extraction";

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
