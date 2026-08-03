## Introduction

The Laravel language server provides framework-aware editor features for Laravel applications. It powers code completions, hover information, diagnostics, document links, go-to definition, and quick fixes for Laravel and Blade code.

## Installation

Install Laravel LSP globally with Composer:

```sh
composer global require laravel/lsp
```

Make sure Composer's global vendor bin directory is on your `PATH`.

## Editor Usage

The server communicates over stdio. Configure your editor to launch the command from the Laravel project root whenever possible.

### Sublime Text

Install and configure the official [Laravel Sublime Text extension](https://github.com/laravel/sublime-extension).

### Zed

Install and configure the official [Laravel Zed extension](https://github.com/laravel/zed-extension).

### VS Code

Install and configure the official [Laravel VS Code extension](https://github.com/laravel/vs-code-extension).

### Cursor

Install and configure the official [Laravel VS Code extension](https://github.com/laravel/vs-code-extension), which is compatible with Cursor.


### Neovim

Neovim 0.11+ is required. Add a custom LSP configuration:

```lua
vim.lsp.config("laravel_lsp", {
    cmd = { "laravel-lsp" },
    filetypes = { "php", "blade" },
    root_markers = { "artisan", "composer.json", ".git" },
})

vim.lsp.enable("laravel_lsp")
```
### Nova

##### v.14 and above
1. Install [Laravel Suite Extension](https://extensions.panic.com/extensions/emran-mr/emran-mr.laravel/).
2. Follow the guidance in `README` and [Nova Language Server Configuration help](https://help.nova.app/settings/languages/) 

##### Any other version
- You could try [Laravel LSP extension](https://extensions.panic.com/extensions/kranial/kranial.laravel-lsp/)

### OpenCode

Enable LSP support in `opencode.json` and add Laravel LSP as a custom server:

```json
{
    "$schema": "https://opencode.ai/config.json",
    "lsp": {
        "laravel-lsp": {
            "command": ["laravel-lsp"],
            "extensions": [".php", ".blade.php"]
        }
    }
}
```

## Features

| Area                  | Capabilities                                      |
| --------------------- | ------------------------------------------------- |
| Routes                | Completions, hovers, diagnostics, document links  |
| Views and Blade       | Completions, hovers, diagnostics, links, fixes    |
| Translations          | Key, locale, and parameter completions; hovers    |
| Config                | Key completions, hovers, diagnostics, links       |
| Environment variables | Completions, hovers, diagnostics, links, fixes    |
| Assets and Mix        | Completions, hovers, diagnostics, links           |
| Middleware            | Completions, hovers, diagnostics, links           |
| Inertia               | Page and property completions, links, diagnostics |
| Livewire components   | Completions, hovers, links                        |
| Auth and policies     | Completions, hovers, diagnostics, links           |
| Container bindings    | Completions, hovers, diagnostics, links           |
| Validation rules      | Completions                                       |
| Controller actions    | Completions, diagnostics, links                   |
| Eloquent              | Completions                                       |

## Configuration

Editor clients pass configuration through the LSP `initializationOptions` object. All options are optional.

### Server Options

| Option                  | Type       | Default                                 | Description                                                                                              |
| ----------------------- | ---------- | --------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| `phpEnvironment`        | `string`   | `"auto"`                                | Select the environment used to detect the PHP command for indexing project data.                         |
| `phpCommand`            | `string[]` | Detected from `phpEnvironment`          | Use an explicit command and arguments, such as `["php"]` or `["./vendor/bin/sail", "php"]`.              |
| `definitionProvider`    | `boolean`  | `true`                                  | Advertise definition support to the editor. Definitions are resolved from enabled document link options. |
| `pestGenerateDocBlocks` | `boolean`  | `true`                                  | Generate Pest helper docblocks and keep them updated when tests or Composer autoload files change.       |
| `pestHelperFilePath`    | `string`   | `"storage/framework/testing/_pest.php"` | Set the Pest helper output path relative to the Laravel project root.                                    |

The `phpEnvironment` option controls which PHP command is used when the server runs project data scripts. It accepts these values:

| Value   | PHP command behavior                                               |
| ------- | ------------------------------------------------------------------ |
| `auto`  | Try Herd, Valet, Sail, Lando, DDEV, then local PHP                 |
| `herd`  | Use `herd which-php`                                               |
| `valet` | Use `valet which-php`                                              |
| `sail`  | Use `./vendor/bin/sail php` when Sail is running                   |
| `lando` | Use `lando php` when available                                     |
| `ddev`  | Use `ddev php` when available                                      |
| `local` | Use the local PHP binary resolved from `php -r 'echo PHP_BINARY;'` |

If detection fails, or an unknown value is provided, the server falls back to `php`.

When `phpCommand` is a non-empty array, it takes precedence over `phpEnvironment`.

### Feature Options

Every feature option is a boolean and defaults to `true`. Set an option to `false` to disable that capability for the corresponding Laravel feature.

| Feature               | Completion                    | Diagnostics                   | Hover                    | Document links          | Code actions      |
| --------------------- | ----------------------------- | ----------------------------- | ------------------------ | ----------------------- | ----------------- |
| Application bindings  | `appBindingCompletion`        | `appBindingDiagnostics`       | `appBindingHover`        | `appBindingLink`        | —                 |
| Assets                | `assetCompletion`             | `assetDiagnostics`            | —                        | `assetLink`             | —                 |
| Authorization         | `authCompletion`              | `authDiagnostics`             | `authHover`              | `authLink`              | —                 |
| Blade components      | `bladeComponentCompletion`    | —                             | `bladeComponentHover`    | `bladeComponentLink`    | —                 |
| Config                | `configCompletion`            | `configDiagnostics`           | `configHover`            | `configLink`            | —                 |
| Controller actions    | `controllerActionCompletion`  | `controllerActionDiagnostics` | —                        | `controllerActionLink`  | —                 |
| Environment variables | `envCompletion`               | `envDiagnostics`              | `envHover`               | `envLink`               | `envViteQuickFix` |
| Inertia               | `inertiaCompletion`           | `inertiaDiagnostics`          | `inertiaHover`           | `inertiaLink`           | —                 |
| Livewire components   | `livewireComponentCompletion` | —                             | `livewireComponentHover` | `livewireComponentLink` | —                 |
| Middleware            | `middlewareCompletion`        | `middlewareDiagnostics`       | `middlewareHover`        | `middlewareLink`        | —                 |
| Mix assets            | `mixCompletion`               | `mixDiagnostics`              | `mixHover`               | `mixLink`               | —                 |
| Path helpers          | —                             | —                             | —                        | `pathsLink`             | —                 |
| Routes                | `routeCompletion`             | `routeDiagnostics`            | `routeHover`             | `routeLink`             | —                 |
| Storage disks         | `storageCompletion`           | `storageDiagnostics`          | —                        | `storageLink`           | —                 |
| Translations          | `translationCompletion`       | `translationDiagnostics`      | `translationHover`       | `translationLink`       | —                 |
| Views                 | `viewCompletion`              | `viewDiagnostics`             | `viewHover`              | `viewLink`              | —                 |

## Supported Platforms

The following platforms are supported:

- macOS arm64 and x64
- Linux arm64 and x64
- Windows x64

## Contributing

Thank you for considering contributing to the Laravel Sublime Text extension! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

Please review [our security policy](https://github.com/laravel/sublime-extension/security/policy) on how to report security vulnerabilities.

## License

The Laravel Sublime Text extension is open-sourced software licensed under the [MIT license](https://opensource.org/license/mit).
