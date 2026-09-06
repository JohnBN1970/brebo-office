import Foundation
import simd

struct OpeningCandidate {
    let boundaries: OpeningBoundarySet
    let widthMm: Double
    let heightMm: Double
    let confidence: Double
    let uncertaintyMm: Double

    static func make(from boundaries: OpeningBoundarySet) -> OpeningCandidate? {
        guard boundaries.hasParallelOpposites else { return nil }

        let width = parallelPlaneSeparationMillimetres(boundaries.left, boundaries.right)
        let height = parallelPlaneSeparationMillimetres(boundaries.top, boundaries.bottom)
        guard width.isFinite, height.isFinite, width > 0, height > 0 else { return nil }

        let residuals = [
            boundaries.left.residualMm,
            boundaries.right.residualMm,
            boundaries.top.residualMm,
            boundaries.bottom.residualMm,
        ]
        let uncertainty = residuals.max() ?? .infinity
        let confidence = max(0, min(1, 1 - (uncertainty / 20.0)))

        return OpeningCandidate(
            boundaries: boundaries,
            widthMm: width,
            heightMm: height,
            confidence: confidence,
            uncertaintyMm: uncertainty
        )
    }

    private static func parallelPlaneSeparationMillimetres(_ a: DetectedPlane, _ b: DetectedPlane) -> Double {
        GeometryMath.distanceMillimetres(from: b.point, toPlane: a)
    }
}
