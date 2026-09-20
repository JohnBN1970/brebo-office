import Foundation

enum OnSiteEvidenceSource: String, Codable {
    case iosGeofence = "ios_geofence"
}

struct OnSitePresenceEvidence: Identifiable, Codable, Equatable {
    let id: UUID
    let projectId: String
    let employeeId: String
    let kind: CrewPresenceEventKind
    let occurredAt: Date
    let source: OnSiteEvidenceSource
    let deviceEventId: UUID

    init(
        id: UUID = UUID(),
        projectId: String,
        employeeId: String,
        kind: CrewPresenceEventKind,
        occurredAt: Date,
        source: OnSiteEvidenceSource = .iosGeofence,
        deviceEventId: UUID
    ) {
        self.id = id
        self.projectId = projectId
        self.employeeId = employeeId
        self.kind = kind
        self.occurredAt = occurredAt
        self.source = source
        self.deviceEventId = deviceEventId
    }
}

struct OnSitePresenceSession: Identifiable, Codable, Equatable {
    let id: UUID
    let projectId: String
    let employeeId: String
    let enteredAt: Date
    let leftAt: Date
    let entryEvidenceId: UUID
    let exitEvidenceId: UUID

    var durationSeconds: TimeInterval {
        leftAt.timeIntervalSince(enteredAt)
    }

    init(
        id: UUID = UUID(),
        projectId: String,
        employeeId: String,
        enteredAt: Date,
        leftAt: Date,
        entryEvidenceId: UUID,
        exitEvidenceId: UUID
    ) {
        self.id = id
        self.projectId = projectId
        self.employeeId = employeeId
        self.enteredAt = enteredAt
        self.leftAt = leftAt
        self.entryEvidenceId = entryEvidenceId
        self.exitEvidenceId = exitEvidenceId
    }
}
