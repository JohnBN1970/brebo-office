import Foundation
import simd

struct RectangularOpeningSelector {
    struct Selection {
        let boundaries: OpeningBoundarySet
        let candidate: OpeningCandidate
    }

    func select(from planes: [DetectedPlane]) -> Selection? {
        let xPlanes = planes.filter { dominantAxis($0.normal) == 0 }
        let yPlanes = planes.filter { dominantAxis($0.normal) == 1 }
        let zPlanes = planes.filter { dominantAxis($0.normal) == 2 }

        guard let verticalPair = bestPair(in: yPlanes, minimumMm: 200, maximumMm: 5000) else {
            return nil
        }

        let xPair = bestPair(in: xPlanes, minimumMm: 200, maximumMm: 5000)
        let zPair = bestPair(in: zPlanes, minimumMm: 200, maximumMm: 5000)
        guard let horizontalPair = betterPair(xPair, zPair) else {
            return nil
        }

        let horizontalSorted = horizontalPair.planes.sorted { axisPosition($0) < axisPosition($1) }
        let verticalSorted = verticalPair.planes.sorted { axisPosition($0) < axisPosition($1) }

        let boundaries = OpeningBoundarySet(
            left: horizontalSorted[0],
            right: horizontalSorted[1],
            top: verticalSorted[1],
            bottom: verticalSorted[0]
        )
        guard let candidate = OpeningCandidate.make(from: boundaries) else {
            return nil
        }
        return Selection(boundaries: boundaries, candidate: candidate)
    }

    private struct PairScore {
        let planes: [DetectedPlane]
        let score: Double
    }

    private func bestPair(in planes: [DetectedPlane], minimumMm: Double, maximumMm: Double) -> PairScore? {
        guard planes.count >= 2 else { return nil }
        var best: PairScore?
        for i in 0..<(planes.count - 1) {
            for j in (i + 1)..<planes.count {
                let a = planes[i]
                let b = planes[j]
                guard GeometryMath.areApproximatelyParallel(a, b) else { continue }
                let separation = GeometryMath.distanceMillimetres(from: b.point, toPlane: a)
                guard separation >= minimumMm, separation <= maximumMm else { continue }
                guard PlaneFitQuality(sampleCount: a.sampleCount, residualMm: a.residualMm).isUsableForResearch,
                      PlaneFitQuality(sampleCount: b.sampleCount, residualMm: b.residualMm).isUsableForResearch else { continue }

                let support = Double(a.sampleCount + b.sampleCount)
                let residualPenalty = max(1, a.residualMm + b.residualMm)
                let score = support / residualPenalty
                if best == nil || score > best!.score {
                    best = PairScore(planes: [a, b], score: score)
                }
            }
        }
        return best
    }

    private func betterPair(_ lhs: PairScore?, _ rhs: PairScore?) -> PairScore? {
        switch (lhs, rhs) {
        case let (.some(a), .some(b)): return a.score >= b.score ? a : b
        case let (.some(a), .none): return a
        case let (.none, .some(b)): return b
        case (.none, .none): return nil
        }
    }

    private func dominantAxis(_ normal: SIMD3<Float>) -> Int {
        let values = [abs(normal.x), abs(normal.y), abs(normal.z)]
        return values.enumerated().max(by: { $0.element < $1.element })?.offset ?? 0
    }

    private func axisPosition(_ plane: DetectedPlane) -> Float {
        switch dominantAxis(plane.normal) {
        case 0: return plane.point.x
        case 1: return plane.point.y
        default: return plane.point.z
        }
    }
}
