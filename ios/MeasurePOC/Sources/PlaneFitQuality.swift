import Foundation

struct PlaneFitQuality: Equatable {
    let sampleCount: Int
    let residualMm: Double

    var isUsableForResearch: Bool {
        sampleCount >= 50 && residualMm.isFinite && residualMm <= 10
    }
}
