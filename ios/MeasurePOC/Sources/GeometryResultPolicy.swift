import Foundation

enum GeometryResultPolicy {
    static func provenanceForScannerOutput() -> ScanEvidenceProvenance {
        .detected
    }

    static var mayBecomeSelectedWithoutHumanReview: Bool {
        false
    }
}
