# Scope stop

This branch intentionally stops before Apple signing and before automatic plane extraction.

Reason: the next unknown is no longer a repository-design question. It is whether the native project actually compiles/tests on hosted macOS/Xcode. Run that gate before adding more scope.

After a green hosted compile, continue with signing/TestFlight. After installation on the LiDAR iPhone, continue with real capture and then automatic plane extraction using actual device evidence.
