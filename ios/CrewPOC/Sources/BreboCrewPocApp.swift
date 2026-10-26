import SwiftUI

@main
struct BreboCrewPocApp: App {
    @UIApplicationDelegateAdaptor(OnSiteAppDelegate.self) private var appDelegate

    var body: some Scene {
        WindowGroup {
            #if DEBUG
            if ProcessInfo.processInfo.arguments.contains("--onsite-history-preview") {
                OnSiteMenuPreview.restoredHistoryWorkspace
            } else if ProcessInfo.processInfo.arguments.contains("--onsite-menu-preview") {
                OnSiteMenuPreview.workspace
            } else {
                CrewContentView()
            }
            #else
            CrewContentView()
            #endif
        }
    }
}
