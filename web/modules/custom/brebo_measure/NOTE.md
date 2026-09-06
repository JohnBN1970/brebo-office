# Implementation note

The v0.1 controller currently maps invalid client input to 400 and other domain/persistence exceptions to 422 for the internal proof. Before an external API is exposed, replace generic exception messages with stable error codes and non-sensitive client responses.
