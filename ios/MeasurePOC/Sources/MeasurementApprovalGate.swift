import Foundation

struct MeasurementApprovalGate {
    enum Decision: Equatable {
        case researchOnly
        case eligibleForReview
    }

    let maximumUncertaintyMm: Double
    let minimumConfidence: Double
    let productionReleaseEnabled: Bool

    init(
        maximumUncertaintyMm: Double = 3,
        minimumConfidence: Double = 0.95,
        productionReleaseEnabled: Bool = false
    ) {
        self.maximumUncertaintyMm = maximumUncertaintyMm
        self.minimumConfidence = minimumConfidence
        self.productionReleaseEnabled = productionReleaseEnabled
    }

    func evaluate(_ candidate: OpeningCandidate) -> Decision {
        guard productionReleaseEnabled,
              candidate.uncertaintyMm <= maximumUncertaintyMm,
              candidate.confidence >= minimumConfidence else {
            return .researchOnly
        }
        return .eligibleForReview
    }
}
