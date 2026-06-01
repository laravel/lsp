# Laravel LSP

## Project

`laravel/lsp` is a Laravel Zero PHP CLI that is compiled into the Laravel LSP binary. It parses PHP and Blade code, extracts framework-aware symbols and context, and contains the Laravel LSP server.

## LSP Server

The LSP server lives in `app/Lsp/` and is invoked via `server` or `server lsp`. It runs as a long-lived process over stdio.

The goal is to provide completion, hover, diagnostic, link, and code-action behavior from the LSP server.

When working on the LSP server, do not write test plans or tests unless explicitly asked.

## Architecture

- The server communicates over stdio using JSON-RPC/LSP framing.
- `Server` owns message dispatch, lifecycle handling, request routing, and notification listeners.
- Request handlers live in `app/Lsp/Methods/`; notification listeners live in `app/Lsp/Listeners/`.
- `Project` owns initialized project URI/path state through `FileUri`, initialization options, the `ScriptRunner`, and the `ProjectIndex` for project data access.
- `Project` stores LSP `initializationOptions`; use its `InteractsWithData` helpers for feature flags and configuration values.
- `DocumentManager` tracks open editor documents, while `Document` owns cached parser-backed analysis for the current document version.
- `ProjectIndex` manages project data providers, receives the container for provider construction, and invalidates matching provider data after watched-file changes. Watched-file notifications should invalidate provider data only unless a feature explicitly needs broader refresh behavior.
- `DataProvider` implementations expose project facts such as routes and views, and own template loading, parsing, watcher patterns, changed-path matching, and cache state.
- When a project fact needs a reusable shaped view, put small helper accessors directly on the relevant `DataProvider`, such as `Auth::policies()`, so feature providers can reuse normalized data without duplicating collection shaping.
- LSP feature behavior is exposed through provider contracts in `app/Lsp/Contracts/` such as `LinkProvider`, `HoverProvider`, `DiagnosticProvider`, and `CompletionProvider`.
- `FeatureRegistry` constructs the active providers for each LSP capability. Request handlers and listeners should use the injected `FeatureRegistry` instead of directly instantiating domain providers.
- Domain features should follow the existing provider structure for their capability. Some features expose one feature class through adapters such as `LinkFeature`, `HoverFeature`, and `DiagnosticFeature`; others, such as routes, expose separate `LinkProvider`, `HoverProvider`, and `DiagnosticProvider` classes that are constructed directly by `FeatureRegistry`.
- Feature providers should stay thin when shared document mapping exists. For routes, `RouteLinkProvider`, `RouteHoverProvider`, and `RouteDiagnosticProvider` own capability-specific configuration checks, while `RouteDocumentMapper` owns route argument detection, accepted argument filtering, route lookup, Volt component lookup, and conversion to links, hovers, and diagnostics.
- Shared mapper-style features should put reusable document traversal in `app/Lsp/Features/Support/DocumentMapper`, keep domain patterns and output conversion in the domain mapper, and use `DetectedArguments` with `Pattern` so features act only on parser-detected arguments.
- Completion providers should follow the same capability-provider shape as links, hovers, and diagnostics: request handlers use `FeatureRegistry`, and feature providers own configuration gates.
- Keep feature providers thin when a mapper exists. Providers should usually check config, construct the mapper or selector, and return the capability result.
- `Document::detect()` is for completed document analysis; `Document::autocomplete($position)` is for incomplete cursor context and should parse content up to the cursor.
- Use `DetectedArguments` and `DetectedArgument` for full-document references such as links, hovers, and diagnostics.
- Use `AutocompleteArguments` and `AutocompleteArgument` for completion contexts where the target argument may not exist yet or may not have parser ranges.
- Reuse `Pattern` for both detected and autocomplete matching. Put parser-shape traversal helpers on `DetectedArgument` or `AutocompleteArgument`, not in feature providers.
- In mapper-style features, `DocumentMapper::completions()` should mirror `links()`, `hover()`, and `diagnostics()`, while `toCompletions()` remains opt-in with a default empty result unless completion support is implemented.
- Register more specific completion providers before broader ones in `FeatureRegistry::completions()` so specialized completions win first.
- Listeners should usually decide when to call providers and publish responses, not whether a feature is enabled.
- Diagnostic publishing should be document-scoped by default: publish on `textDocument/didOpen` and `textDocument/didChange`, clear on `textDocument/didClose`, and avoid publishing all open documents unless there is a concrete product requirement.
- Detection helpers select parser-detected arguments so feature logic can share the same document-analysis flow.

## Provider Migration Style

- Prefer parser and template contracts over defensive array probing. Once data has passed through `DetectedArguments`, `Pattern`, or a shaped `DataProvider` accessor, read the guaranteed keys directly. Keep checks only for legitimate optional cases such as missing user arguments, nullable parser fields, ambiguous matches, or incomplete editor input.
- Keep parser-shape traversal in detection classes. Put reusable argument helpers such as string and array string extraction, nested ranges, and position containment on `DetectedArgument` instead of duplicating parser traversal in feature providers.
- Put pattern-building namespace helpers on `Pattern`, such as `Pattern::contract()`, `Pattern::support()`, `Pattern::facade()`, and `Pattern::containerAttribute()`. Do not reintroduce `contract`, `support`, `facade`, or attribute namespace helpers on individual providers.
- Keep project-relative URI and link construction on `Project` through helpers such as `target()` and `link()`. Providers should not duplicate path joining and `#L` handling when the project helper fits.
- Mapper providers should convert detected arguments into links, hovers, and diagnostics with minimal branching. Use `map()` plus `filter()` for one-response-or-null conversions, and use `flatMap()` only when one input can intentionally produce many responses.
- Hover matching should use `DetectedArgument::containsPosition()` when a provider may accept array arguments, because array parser nodes may not have top-level ranges while their nested string values do.
- Preserve behavior decisions separately from shape checks. Examples of behavior checks that should remain are skipping empty values, not linking ambiguous policy matches, ignoring wildcard route names, and requiring model matches only for calls where a model argument exists.
- For route features, keep `RouteDocumentMapper` focused on route-name references and route-name completions. Keep route-parameter completions in a separate provider with its own parameter-argument patterns.

## LSP PHP Templates

- LSP data templates live in `app/Lsp/Data/Templates/` and are executed in the user's Laravel app through `ScriptRunner`.
- `ScriptRunner` prepends `app/Lsp/Data/Templates/global.php` before executing templates through Laravel Tinker.
- Put shared template helpers in `global.php`; templates should use `LspHelper` methods instead of duplicating helper closures.
- Keep LSP data templates self-contained and use `LspHelper` methods instead of duplicating helper closures.

## Code Style

- PHP 8.2+. Use native types everywhere.
- Add Laravel-style docblocks to every method and property. Do not add `@param`, `@return`, or `@var` annotations when native types already fully express the type. Only add them for array generics, array shapes, closure signatures, or class-strings.
- Use `declare(strict_types=1)` in all LSP and transport files.
- Use constructor property promotion.
- Follow existing patterns. When adding a new LSP request method, mirror the structure of existing handlers in `app/Lsp/Methods/`. When adding a notification handler, mirror existing listeners in `app/Lsp/Listeners/`.
- When changing LSP behavior, preserve existing user-facing settings unless the product behavior intentionally changes.
- Prefer parser-detected argument indexes over broad argument scans so LSP features diagnose or link only the intended argument positions.
