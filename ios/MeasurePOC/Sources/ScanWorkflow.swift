import Foundation

enum ScanWorkflowStep: String, CaseIterable {
    case chooseOpening
    case capture
    case analyse
    case reviewDetectedGeometry
    case compareReference
    case saveEvidence
}

enum ScanWorkflow {
    static let poc = ScanWorkflowStep.allCases
}
