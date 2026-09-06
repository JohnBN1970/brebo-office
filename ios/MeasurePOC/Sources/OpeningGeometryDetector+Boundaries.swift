import Foundation

extension OpeningGeometryDetector {
    func geometry(from boundaries: OpeningBoundarySet) throws -> OpeningGeometry {
        guard let candidate = OpeningCandidate.make(from: boundaries) else {
            throw DetectionError.noOpeningCandidate
        }
        return OpeningGeometry(
            widthTopMm: candidate.widthMm,
            widthMiddleMm: candidate.widthMm,
            widthBottomMm: candidate.widthMm,
            heightLeftMm: candidate.heightMm,
            heightMiddleMm: candidate.heightMm,
            heightRightMm: candidate.heightMm,
            confidence: candidate.confidence,
            uncertaintyMm: candidate.uncertaintyMm
        )
    }
}
