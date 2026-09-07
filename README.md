## Introduction

Laravel LSP provides framework-aware editor features for Laravel applications, including completions, hover information, diagnostics, document links, Go to Definition, and code actions for Laravel and Blade code.

## Installation

Install Laravel LSP globally with Composer:

```sh
composer global require laravel/lsp
```

Ensure Composer's global bin directory is on your `PATH`.

## Editor Usage

The server communicates over stdio. Configure your editor to launch it from the Laravel project root whenever possible.

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
    root_dir = function(bufnr, on_dir)
        local root = vim.fs.root(bufnr, "artisan")

        if root then
            on_dir(root)
        end
    end,
})

vim.lsp.enable("laravel_lsp")
```

The `root_dir` callback only starts the server when an `artisan` file is found, so the server is not launched for PHP projects that are not Laravel applications.

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

| Area                  | Capabilities                                                |
| --------------------- | ----------------------------------------------------------- |
| Routes                | Completions, hovers, diagnostics, document links            |
| Views and Blade       | Completions, hovers, diagnostics, document links, code actions |
| Translations          | Key, locale, and parameter completions; hovers               |
| Config                | Key completions, hovers, diagnostics, document links         |
| Environment variables | Completions, hovers, diagnostics, document links, code actions |
| Assets and Mix        | Completions, hovers, diagnostics, document links             |
| Middleware            | Completions, hovers, diagnostics, document links             |
| Inertia               | Page and property completions, diagnostics, document links, code actions |
| Livewire components   | Completions, hovers, document links                          |
| Auth and policies     | Completions, hovers, diagnostics, document links             |
| Container bindings    | Completions, hovers, diagnostics, document links             |
| Validation rules      | Completions                                                 |
| Controller actions    | Completions, diagnostics, document links                     |
| Eloquent              | Completions                                                 |

## Configuration

Editor clients pass configuration through the LSP `initializationOptions` object. All options are optional.

### Server Options

| Option                  | Type       | Default                                 | Description                                                                                              |
| ----------------------- | ---------- | --------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| `phpEnvironment`        | `string`   | `"auto"`                                | Select the environment used to detect the PHP command for indexing project data.                         |
| `phpCommand`            | `string[]` | Detected from `phpEnvironment`          | Use an explicit command and arguments, such as `["php"]` or `["./vendor/bin/sail", "php"]`.              |
| `memoryLimit`           | `string`   | `"512M"`                                | Set the LSP server process `memory_limit` during initialize. Use PHP shorthand such as `"512M"`, `"1G"`, or `"-1"`. |
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

The `memoryLimit` option is applied to the LSP server process during initialize, before project index JSON is decoded. Official editor binaries otherwise default to PHP's 128M `memory_limit`. Invalid values fall back to `"512M"`. This option does not change the PHP command used to run project scripts.

### Feature Options

Every feature option is a boolean that defaults to `true`. Set an option to `false` to disable the corresponding capability.

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

Thank you for considering contributing to Laravel LSP! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

To help keep the Laravel community welcoming to all, please review and follow the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

See [our security policy](https://github.com/laravel/lsp/security/policy) for information on reporting security vulnerabilities.

## License

Laravel LSP is open-source software licensed under the [MIT license](https://opensource.org/license/mit).
