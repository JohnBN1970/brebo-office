import { describe, expect, it } from "vitest";
import { pdfEmbeddedPositions } from "../src/pdf-embedded-positions";

describe("embedded PDF text positions adapter", () => {
  const mock = () => ({
    promise: Promise.resolve({
      numPages: 1,
      getPage: async () => ({
        view: [0, 0, 595, 842],
        getTextContent: async () => ({ items: [
          { str: "PVC40101", transform: [1, 0, 0, 1, 51, 515] },
          { str: "96,00", transform: [1, 0, 0, 1, 422.7, 515] },
        ] }),
      }),
    }),
  });
  it("converts PDF user-space coordinates to normalized top-left positions", async () => {
    const blocks = await pdfEmbeddedPositions(new Uint8Array([37, 80, 68, 70]), mock);
    expect(blocks).toHaveLength(2);
    expect(blocks[0]?.x).toBeCloseTo(51 / 595);
    expect(blocks[0]?.y).toBeCloseTo(327 / 842);
  });
  it("returns no layout for failed PDF parsing", async () => {
    expect(await pdfEmbeddedPositions(new Uint8Array([1]), () => {
      throw new Error("unsupported runtime");
    })).toEqual([]);
  });
});
