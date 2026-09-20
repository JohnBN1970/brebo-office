import Foundation

struct OnSitePresenceSessionBuilder {
    func build(from evidence: [OnSitePresenceEvidence]) -> [OnSitePresenceSession] {
        let grouped = Dictionary(grouping: evidence) { item in
            "\(item.employeeId)::\(item.projectId)"
        }

        return grouped.values.flatMap { items in
            let ordered = items.sorted { $0.occurredAt < $1.occurredAt }
            var openEntry: OnSitePresenceEvidence?
            var sessions: [OnSitePresenceSession] = []

            for item in ordered {
                switch item.kind {
                case .enteredProject:
                    openEntry = item
                case .leftProject:
                    guard let entry = openEntry, item.occurredAt >= entry.occurredAt else { continue }
                    sessions.append(
                        OnSitePresenceSession(
                            projectId: item.projectId,
                            employeeId: item.employeeId,
                            enteredAt: entry.occurredAt,
                            leftAt: item.occurredAt,
                            entryEvidenceId: entry.id,
                            exitEvidenceId: item.id
                        )
                    )
                    openEntry = nil
                }
            }

            return sessions
        }
        .sorted { $0.enteredAt < $1.enteredAt }
    }
}
