import Foundation

enum OpeningObservationFactory {
    static func make(from geometry: OpeningGeometry) -> [ScanEvidence] {
        [
            ScanEvidence(key: "opening.width.top", provenance: .detected, valueMm: geometry.widthTopMm, uncertaintyMm: geometry.uncertaintyMm, confidence: geometry.confidence),
            ScanEvidence(key: "opening.width.middle", provenance: .detected, valueMm: geometry.widthMiddleMm, uncertaintyMm: geometry.uncertaintyMm, confidence: geometry.confidence),
            ScanEvidence(key: "opening.width.bottom", provenance: .detected, valueMm: geometry.widthBottomMm, uncertaintyMm: geometry.uncertaintyMm, confidence: geometry.confidence),
            ScanEvidence(key: "opening.height.left", provenance: .detected, valueMm: geometry.heightLeftMm, uncertaintyMm: geometry.uncertaintyMm, confidence: geometry.confidence),
            ScanEvidence(key: "opening.height.middle", provenance: .detected, valueMm: geometry.heightMiddleMm, uncertaintyMm: geometry.uncertaintyMm, confidence: geometry.confidence),
            ScanEvidence(key: "opening.height.right", provenance: .detected, valueMm: geometry.heightRightMm, uncertaintyMm: geometry.uncertaintyMm, confidence: geometry.confidence),
        ]
    }
}
