import assert from "node:assert/strict";
import * as pdfjs from "pdfjs-dist/legacy/build/pdf.js";

// Small synthetic PDF with actual embedded text and a valid xref table.
// This is not a customer invoice and contains no confidential data.
function pdfWithText() {
  const stream = "BT /F1 12 Tf 51 515 Td (PVC40101) Tj ET\nBT /F1 12 Tf 422 515 Td (96,00) Tj ET\n";
  const objects = [
    "<< /Type /Catalog /Pages 2 0 R >>",
    "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
    "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
    "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
    `<< /Length ${Buffer.byteLength(stream)} >>\nstream\n${stream}endstream`,
  ];
  let output = "%PDF-1.4\n";
  const offsets = [0];
  for (let i = 0; i < objects.length; i++) {
    offsets.push(Buffer.byteLength(output));
    output += `${i + 1} 0 obj\n${objects[i]}\nendobj\n`;
  }
  const xref = Buffer.byteLength(output);
  output += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
  for (const offset of offsets.slice(1)) output += `${String(offset).padStart(10, "0")} 00000 n \n`;
  output += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF\n`;
  return new Uint8Array(Buffer.from(output));
}
const { pdfEmbeddedPositions } = await import("../src/pdf-embedded-positions.ts");
const getDocument = pdfjs.getDocument ?? pdfjs.default?.getDocument;
assert.equal(typeof getDocument, "function");
const positions = await pdfEmbeddedPositions(pdfWithText(), getDocument);
assert.ok(positions.some(p => p.text === "PVC40101" && Math.abs(p.x - 51 / 595) < 0.01));
assert.ok(positions.some(p => p.text === "96,00" && Math.abs(p.y - 327 / 842) < 0.02));
console.log("PDFJS_REAL_PDF_POSITION_SMOKE_OK");
