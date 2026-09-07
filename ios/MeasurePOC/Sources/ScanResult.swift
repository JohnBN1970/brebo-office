import Foundation

struct ScanResult {
    let candidate: OpeningCandidate?
    let depthFrames: Int
    let meshAnchors: Int
    let approval: MeasurementApprovalGate.Decision

    var summary: String {
        guard let candidate else {
            return "Nog geen betrouwbare sparingsgeometrie gevonden"
        }
        return String(
            format: "Kandidaat %.0f x %.0f mm - onzekerheid %.1f mm - %@",
            candidate.widthMm,
            candidate.heightMm,
            candidate.uncertaintyMm,
            approval == .researchOnly ? "ONDERZOEK" : "CONTROLE"
        )
    }
}
