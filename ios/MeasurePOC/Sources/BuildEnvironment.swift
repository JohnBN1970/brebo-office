import Foundation

enum BuildEnvironment: String {
    case hostedMacOS
    case physicalIPhone

    static let compileGate: BuildEnvironment = .hostedMacOS
    static let sensorGate: BuildEnvironment = .physicalIPhone
}
