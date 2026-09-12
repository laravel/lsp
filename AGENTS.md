# Laravel LSP

## Project

`laravel/lsp` is a Laravel Zero PHP CLI that is compiled into the Laravel LSP binary. It parses PHP and Blade code, extracts framework-aware symbols and context, and contains the Laravel LSP server.

## LSP Server

The LSP server lives in `app/Lsp/` and is invoked via `server` or `server lsp`. It runs as a long-lived process over stdio.

It provides completion, hover, diagnostic, link, and code-action behavior.

## Architecture

- The server communicates over stdio using JSON-RPC/LSP framing.
- `Server` owns message dispatch, lifecycle handling, request routing, and notification listeners.
- Request handlers live in `app/Lsp/Methods/`; notification listeners live in `app/Lsp/Listeners/`.
- `Project` owns initialized project URI/path state through `FileUri`, initialization options, the `ScriptRunner`, and the `ProjectIndex` for project data access.
- `Project` stores LSP `initializationOptions`, and its `InteractsWithData` helpers expose feature flags and configuration values.
- `DocumentManager` tracks open editor documents, while `Document` owns cached parser-backed analysis for the current document version.
- `ProjectIndex` manages project data providers, receives the container for provider construction, and invalidates matching provider data after watched-file changes.
- `DataProvider` implementations expose project facts such as routes and views, and own template loading, parsing, watcher patterns, changed-path matching, and cache state.
- LSP feature behavior is exposed through provider contracts in `app/Lsp/Contracts/` such as `LinkProvider`, `HoverProvider`, `DiagnosticProvider`, and `CompletionProvider`.
- `FeatureRegistry` constructs the active providers for each LSP capability and supplies them to request handlers and listeners.
- Each domain exposes one thin provider class per capability, all constructed by `FeatureRegistry`. Mapper-style domains delegate to their `*DocumentMapper`.
- Shared document traversal for mapper-style features lives in `app/Lsp/Features/Support/DocumentMapper`; domain mappers own their patterns and output conversion.
- Route capability providers own capability-specific configuration, while `RouteDocumentMapper` owns route argument detection, filtering, lookup, Volt component lookup, and output conversion.
- `Document::detect()` analyzes completed documents. `Document::autocomplete($position)` analyzes incomplete cursor context up to the cursor.
- `DetectedArguments` and `DetectedArgument` represent full-document references used by links, hovers, and diagnostics.
- `AutocompleteArguments` and `AutocompleteArgument` represent completion contexts where a target argument may be incomplete or lack parser ranges.
- `Pattern` provides matching for both detected and autocomplete arguments.
- Listeners invoke providers and publish responses for document notifications.
- `Transport` implementations own JSON-RPC framing over stdio. `AmpStdioTransport` dispatches requests concurrently, while `StdioTransport` handles one message at a time and is used on Windows.
- File watching lives in `app/Lsp/Watchers/`. `RegisterFileWatchers` registers the union of watcher patterns on `initialized`, `NotifyFileWatchers` fans `workspace/didChangeWatchedFiles` out to each `FileWatcher`, and `DataProviderWatcher` invalidates matching `ProjectIndex` entries.

## Custom Requests

- `laravel/data` returns indexed project facts to the client. It takes a `name` parameter and responds with that provider's data, loading it on first use.
- Valid names are the `ProjectIndex` provider keys: `appBindings`, `assets`, `auth`, `bladeComponents`, `configs`, `controllers`, `customBladeDirectives`, `debugInfo`, `env`, `inertiaViews`, `middleware`, `mixManifest`, `models`, `paths`, `routes`, `tests`, `translations`, `views`.

## LSP PHP Templates

- LSP data templates live in `app/Lsp/Data/Templates/` and are executed in the user's Laravel app through `ScriptRunner`.
- `ScriptRunner` prepends `app/Lsp/Data/Templates/global.php` before executing templates through Laravel Tinker.
- Shared template helpers live in `global.php` and are exposed through `LspHelper`.
