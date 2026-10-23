import SwiftUI

@main
struct BreboCrewPocApp: App {
    @UIApplicationDelegateAdaptor(OnSiteAppDelegate.self) private var appDelegate

    var body: some Scene {
        WindowGroup {
            CrewContentView()
        }
    }
}
