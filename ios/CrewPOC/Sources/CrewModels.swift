import Foundation
import CoreLocation

struct CrewProjectZone: Identifiable, Equatable {
    let id: String
    let projectName: String
    let latitude: Double
    let longitude: Double
    let radiusMetres: Double

    var coordinate: CLLocationCoordinate2D {
        CLLocationCoordinate2D(latitude: latitude, longitude: longitude)
    }
}

enum CrewPresenceState: String, Codable {
    case unknown
    case outside
    case present
}

enum CrewPresenceEventKind: String, Codable {
    case enteredProject
    case leftProject
}

struct CrewPresenceEvent: Identifiable, Equatable {
    let id: UUID
    let projectId: String
    let kind: CrewPresenceEventKind
    let occurredAt: Date

    init(id: UUID = UUID(), projectId: String, kind: CrewPresenceEventKind, occurredAt: Date = Date()) {
        self.id = id
        self.projectId = projectId
        self.kind = kind
        self.occurredAt = occurredAt
    }
}
