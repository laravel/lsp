# Laravel LSP

The Laravel language server provides framework-aware editor features for Laravel applications. It runs over stdio using the Language Server Protocol and powers completions, hovers, diagnostics, document links, definitions, and quick fixes for Laravel and Blade code.

## Requirements

- **PHP 8.2+**
- **Composer**
- **Laravel application** - the server indexes framework data from the project root
- **LSP-compatible editor or client** - such as Sublime Text, Neovim, Cursor, or OpenCode

## Installation

Clone the repository and install dependencies:

```sh
gh repo clone laravel/lsp
cd lsp
composer install
```

You can run the server from source with:

```sh
php server
```

## Setup Alias

To use the `laravel-lsp` command from anywhere, add an alias to your shell configuration:

**For Zsh (macOS default):**

```sh
echo 'alias laravel-lsp="php '$(pwd)'/server"' >> ~/.zshrc
source ~/.zshrc
```

**For Bash:**

```sh
echo 'alias laravel-lsp="php '$(pwd)'/server"' >> ~/.bashrc
source ~/.bashrc
```

Or manually add the alias to your `~/.zshrc` or `~/.bashrc` file:

```sh
alias laravel-lsp="php /path/to/lsp/server"
```

If you are using a downloaded standalone binary, make it executable and put it on your `PATH` instead:

```sh
chmod +x /path/to/server-vX.Y.Z-arm64-darwin
mv /path/to/server-vX.Y.Z-arm64-darwin /usr/local/bin/laravel-lsp
```

## Quick Start

**Configure** - Point your editor or LSP client at the server command:

    ```sh
    php /path/to/lsp/server lsp
    ```

**Open Laravel** - Open a Laravel project root in your editor so the server can index routes, views, translations, config, and other project data.

## Editor Usage

The server communicates over stdio. Configure your editor to launch the command from the Laravel project root whenever possible.

### Sublime Text

Install the [LSP](https://packagecontrol.io/packages/LSP) package, then add a client configuration in `Preferences: LSP Settings`:

```json
{
    "clients": {
        "laravel-lsp": {
            "enabled": true,
            "command": ["php", "/path/to/lsp/server"],
            "selector": "embedding.php | text.html.blade"
        }
    }
}
```

### Neovim

With Neovim 0.11+, add a custom LSP configuration:

```lua
vim.lsp.config("laravel_lsp", {
    cmd = { "php", "/path/to/lsp/server" },
    filetypes = { "php", "blade" },
    root_markers = { "artisan", "composer.json", ".git" },
})

vim.lsp.enable("laravel_lsp")
```

With `nvim-lspconfig`, register the server if it is not already available:

```lua
local lspconfig = require("lspconfig")
local configs = require("lspconfig.configs")

if not configs.laravel_lsp then
    configs.laravel_lsp = {
        default_config = {
            cmd = { "php", "/path/to/lsp/server" },
            filetypes = { "php", "blade" },
            root_dir = lspconfig.util.root_pattern("artisan", "composer.json", ".git"),
        },
    }
end

lspconfig.laravel_lsp.setup({})
```

### Cursor

Cursor supports VS Code extensions, so the simplest setup is to install the Laravel extension that bundles or configures Laravel LSP.

For local development against this repository, use a VS Code-compatible custom LSP extension or client and point it at:

```sh
php /path/to/lsp/server
```

### OpenCode

Enable LSP support in `opencode.json` and add Laravel LSP as a custom server:

```json
{
    "$schema": "https://opencode.ai/config.json",
    "lsp": {
        "laravel-lsp": {
            "command": ["php", "/path/to/lsp/server"],
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

Editor clients pass configuration through LSP `initializationOptions`.

| Option               | Default | Description                                                    |
| -------------------- | ------- | -------------------------------------------------------------- |
| `phpEnvironment`     | `auto`  | Detect which PHP environment to use for indexing project data  |
| `phpCommand`         | auto    | Explicit PHP command array, such as `["php"]` or Sail commands |
| `definitionProvider` | `false` | Enable definition support when the client should request it    |

The `phpEnvironment` option controls which PHP command is used when the server runs project data scripts. It accepts these values:

| Value   | PHP command behavior                                            |
| ------- | --------------------------------------------------------------- |
| `auto`  | Try Herd, Valet, Sail, Lando, DDEV, then local PHP              |
| `herd`  | Use `herd which-php`                                            |
| `valet` | Use `valet which-php`                                           |
| `sail`  | Use `./vendor/bin/sail php` when Sail is running                |
| `lando` | Use `lando php` when available                                  |
| `ddev`  | Use `ddev php` when available                                   |
| `local` | Use the local PHP binary resolved from `php -r 'echo PHP_BINARY;'` |

If detection fails, or an unknown value is provided, the server falls back to `php`.

Most feature providers can be enabled or disabled individually by passing boolean initialization options. For example:

```json
{
    "routeCompletion": true,
    "routeDiagnostics": true,
    "viewDiagnostics": false,
    "translationHover": true
}
```

Common feature option suffixes are `Completion`, `Diagnostics`, `Hover`, and `Link`, such as `routeCompletion`, `configDiagnostics`, `envHover`, or `bladeComponentLink`.

## Links

- [Laravel](https://laravel.com)
- [Laravel LSP source](https://github.com/laravel/lsp)
