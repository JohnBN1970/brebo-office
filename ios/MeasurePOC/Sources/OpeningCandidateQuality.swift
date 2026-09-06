import Foundation

struct OpeningCandidateQuality {
    static func isUsable(_ boundaries: OpeningBoundarySet) -> Bool {
        let planes = [boundaries.left, boundaries.right, boundaries.top, boundaries.bottom]
        return boundaries.hasParallelOpposites && planes.allSatisfy {
            PlaneFitQuality(sampleCount: $0.sampleCount, residualMm: $0.residualMm).isUsableForResearch
        }
    }
}
