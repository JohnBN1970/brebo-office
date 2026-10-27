/**
 * Experimental embedded-text PDF positions for Cloudflare Workers.
 * No OCR; failure must fall back to existing Workers AI text extraction.
 * The caller must provide a pdfjs-dist getDocument implementation.
 */
export type PdfPosition = { page: number; text: string; x: number; y: number };

export async function pdfEmbeddedPositions(
  bytes: Uint8Array,
  getDocument: (options: Record<string, unknown>) => { promise: Promise<unknown>; destroy?: () => Promise<void> },
): Promise<PdfPosition[]> {
  if (bytes.length === 0 || bytes.length > 15 * 1024 * 1024) return [];
  let task: ReturnType<typeof getDocument> | undefined;
  try {
    task = getDocument({
      data: bytes,
      useSystemFonts: false,
      disableFontFace: true,
      isEvalSupported: false,
    });
    const document = await task.promise as {
      numPages: number;
      getPage: (page: number) => Promise<{
        view: number[];
        getTextContent: () => Promise<{ items: unknown[] }>;
      }>;
    };
    if (!Number.isInteger(document.numPages) || document.numPages < 1 || document.numPages > 100) return [];
    const result: PdfPosition[] = [];
    for (let page = 1; page <= document.numPages; page++) {
      const pdfPage = await document.getPage(page);
      const width = pdfPage.view[2] - pdfPage.view[0];
      const height = pdfPage.view[3] - pdfPage.view[1];
      if (!(width > 0 && height > 0)) return [];
      const content = await pdfPage.getTextContent();
      for (const raw of content.items) {
        if (!raw || typeof raw !== "object") continue;
        const item = raw as { str?: unknown; transform?: unknown };
        if (typeof item.str !== "string" || !Array.isArray(item.transform)
          || item.transform.length < 6) continue;
        const text = item.str.trim();
        if (text === "") continue;
        const transform = item.transform as number[];
        const x = (transform[4] - pdfPage.view[0]) / width;
        const y = (pdfPage.view[3] - transform[5]) / height;
        if (text.length > 500 || !Number.isFinite(x) || !Number.isFinite(y)
          || x < 0 || x > 1 || y < 0 || y > 1) return [];
        result.push({ page, text, x, y });
        if (result.length > 2000) return [];
      }
    }
    return result;
  } catch {
    return [];
  } finally {
    try { await task?.destroy?.(); } catch { /* optional cleanup */ }
  }
}
