import Foundation

enum ScanBuildGate: Equatable {
    case awaitingHostedCompile
    case readyForSigning

    static let current: ScanBuildGate = .awaitingHostedCompile
}
