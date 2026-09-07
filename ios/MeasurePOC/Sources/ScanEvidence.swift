import Foundation

enum ScanEvidenceProvenance: String, Codable {
    case measured
    case detected
    case calculated
}

struct ScanEvidence: Codable {
    let key: String
    let provenance: ScanEvidenceProvenance
    let valueMm: Double
    let uncertaintyMm: Double?
    let confidence: Double?
}
