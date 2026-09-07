import Foundation

struct ScanStatus {
    let depthFrames: Int
    let meshAnchors: Int
    let verdict: ScanQualityGate.Verdict

    var message: String {
        switch verdict {
        case .insufficientCoverage:
            return "Beweeg rustig rondom de volledige sparing"
        case .readyForGeometry:
            return "Voldoende ruimtedata - geometrie kan worden geanalyseerd"
        }
    }
}
