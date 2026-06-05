#!/usr/bin/env bash

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
RESET='\033[0m'

abort() {
    echo -e "${RED}Error: $1${RESET}" >&2
    exit 1
}

info() {
    echo -e "${CYAN}$1${RESET}"
}

success() {
    echo -e "${GREEN}$1${RESET}"
}

# Check we're on main
BRANCH=$(git rev-parse --abbrev-ref HEAD)

if [ "$BRANCH" != "main" ]; then
    abort "You must be on the 'main' branch to release. Currently on: $BRANCH"
fi

# Fetch latest remote state
info "Fetching latest remote state..."
git fetch --tags origin

# Check for dirty files
if ! git diff --quiet || ! git diff --cached --quiet; then
    abort "You have uncommitted changes. Please commit or stash them before releasing."
fi

if [ -n "$(git ls-files --others --exclude-standard)" ]; then
    abort "You have untracked files. Please commit or remove them before releasing."
fi

# Check local is not ahead of remote
LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/main 2>/dev/null || echo "")

if [ -z "$REMOTE" ]; then
    abort "Could not find remote tracking branch 'origin/main'."
fi

AHEAD=$(git rev-list origin/main..HEAD --count)

if [ "$AHEAD" -gt 0 ]; then
    abort "Your branch is $AHEAD commit(s) ahead of origin/main. Push before releasing."
fi

# Check remote is not ahead of local
BEHIND=$(git rev-list HEAD..origin/main --count)

if [ "$BEHIND" -gt 0 ]; then
    abort "Your branch is $BEHIND commit(s) behind origin/main. Pull before releasing."
fi

# Determine current latest tag
CURRENT_TAG=$(git tag --sort=-v:refname | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' | head -1)

if [ -z "$CURRENT_TAG" ]; then
    CURRENT_TAG="v0.0.0"
    info "No existing tags found. Starting from $CURRENT_TAG."
else
    info "Current latest tag: ${BOLD}$CURRENT_TAG${RESET}"
fi

# Parse version components
VERSION="${CURRENT_TAG#v}"
MAJOR=$(echo "$VERSION" | cut -d. -f1)
MINOR=$(echo "$VERSION" | cut -d. -f2)
PATCH=$(echo "$VERSION" | cut -d. -f3)

NEXT_PATCH="v${MAJOR}.${MINOR}.$((PATCH + 1))"
NEXT_MINOR="v${MAJOR}.$((MINOR + 1)).0"
NEXT_MAJOR="v$((MAJOR + 1)).0.0"

echo ""
echo -e "${BOLD}Select version bump type:${RESET}"
echo -e "  1) patch  -> ${NEXT_PATCH}"
echo -e "  2) minor  -> ${NEXT_MINOR}"
echo -e "  3) major  -> ${NEXT_MAJOR}"
echo ""

while true; do
    read -r -p "Choice [1/2/3]: " CHOICE

    case "$CHOICE" in
        1|patch)
            NEW_TAG="$NEXT_PATCH"
            break
            ;;
        2|minor)
            NEW_TAG="$NEXT_MINOR"
            break
            ;;
        3|major)
            NEW_TAG="$NEXT_MAJOR"
            break
            ;;
        *)
            echo -e "${YELLOW}Please enter 1, 2, or 3.${RESET}"
            ;;
    esac
done

echo ""
echo -e "New tag will be: ${BOLD}${GREEN}${NEW_TAG}${RESET}"
echo ""

read -r -p "Confirm and release $NEW_TAG? [y/N] " CONFIRM

if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 0
fi

if [ -f ".env" ]; then
    mv .env .env.bak
    info "Backed up .env to .env.bak"
fi

info "Building binary..."
php server app:build laravel-lsp --build-version="$NEW_TAG"

if [ -f ".env.bak" ]; then
    mv .env.bak .env
    info "Restored .env from .env.bak"
fi

info "Smoke testing binary..."

if [ ! -f "builds/laravel-lsp" ]; then
    abort "Build output builds/laravel-lsp not found."
fi

SMOKE_VERSION=$(./builds/laravel-lsp --version 2>&1) || abort "Binary failed to execute: $SMOKE_VERSION"

if ! echo "$SMOKE_VERSION" | grep -qF "$NEW_TAG"; then
    abort "Binary reported unexpected version. Expected '$NEW_TAG', got: $SMOKE_VERSION"
fi

./builds/laravel-lsp list >/dev/null 2>&1 || abort "Binary failed to list commands - bundled dependencies may be broken."

success "Smoke test passed: $SMOKE_VERSION"

info "Committing build..."
git add builds/laravel-lsp
git commit -m "Build $NEW_TAG"
git tag "$NEW_TAG"
git push origin main "$NEW_TAG"

info "Pushed $NEW_TAG. GitHub Actions will create the release."

REMOTE_URL=$(git remote get-url origin)
REPO_PATH=$(echo "$REMOTE_URL" | sed -E 's|.*github\.com[:/]||;s|\.git$||')
REPO_URL="https://github.com/${REPO_PATH}"

echo ""
success "Release $NEW_TAG started."
echo ""
echo -e "  Release:  ${CYAN}${REPO_URL}/releases/tag/${NEW_TAG}${RESET}"
echo ""
