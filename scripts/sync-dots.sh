#!/usr/bin/env bash
# sync-dots.sh — Orphan overlay sync utility
#
# Checks out every file tracked by config-assets into the working tree without
# tracking them in the current branch index. Also installs a git post-checkout
# hook so this runs automatically on branch switch.
#
# Usage: ./scripts/sync-dots.sh

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
NC='\033[0m'

log()  { echo -e "${CYAN}[sync-dots]${NC} $*"; }
ok()   { echo -e "${GREEN}[sync-dots]${NC} $*"; }
err()  { echo -e "${RED}[sync-dots] ERROR:${NC} $*"; }

CONFIG_BRANCH="config-assets"
ROOT=$(git rev-parse --show-toplevel 2>/dev/null || echo ".")

# Git restore can invoke post-checkout internally; avoid recursive hook runs.
if [[ "${UPSELLBAY_SYNC_DOTS_RUNNING:-}" = "1" ]]; then
	exit 0
fi

export UPSELLBAY_SYNC_DOTS_RUNNING=1

# ── Install post-checkout hook ──────────────────────────────────────
install_hook() {
	local hook_dst="${ROOT}/.git/hooks/post-checkout"
	local hook_src="${ROOT}/.githooks/post-checkout"

	if [[ -f "$hook_dst" ]]; then
		return 0  # already installed
	fi

	if [[ ! -f "$hook_src" ]]; then
		return 0  # source not available yet (first run before overlay)
	fi

	mkdir -p "${ROOT}/.git/hooks"
	cp "$hook_src" "$hook_dst"
	chmod +x "$hook_dst"
	ok "Installed post-checkout hook → auto-syncs overlay on branch switch."
}

CURRENT_BRANCH=$(git symbolic-ref --short HEAD 2>/dev/null || true)

if [[ -z "$CURRENT_BRANCH" ]]; then
	err "Not on any branch (detached HEAD?). Exiting."
	exit 1
fi

log "Current branch: ${CURRENT_BRANCH}"
log "Syncing overlay from: ${CONFIG_BRANCH}"

if ! git show-ref --verify --quiet "refs/heads/${CONFIG_BRANCH}"; then
	err "Branch '${CONFIG_BRANCH}' does not exist."
	err "Create it first: git checkout --orphan ${CONFIG_BRANCH}"
	exit 1
fi

PATHSPEC_FILE="$(mktemp)"
TRACKED_FILE="$(mktemp)"
COLLISION_FILE="$(mktemp)"
cleanup() {
	rm -f "$PATHSPEC_FILE"
	rm -f "$TRACKED_FILE"
	rm -f "$COLLISION_FILE"
}
trap cleanup EXIT

git ls-tree -r --name-only "${CONFIG_BRANCH}" > "$PATHSPEC_FILE"

if [[ ! -s "$PATHSPEC_FILE" ]]; then
	err "Branch '${CONFIG_BRANCH}' does not track any files."
	exit 1
fi

git ls-files > "$TRACKED_FILE"
comm -12 <(sort "$PATHSPEC_FILE") <(sort "$TRACKED_FILE") > "$COLLISION_FILE"

if [[ -s "$COLLISION_FILE" ]]; then
	err "Overlay paths are tracked on '${CURRENT_BRANCH}', refusing to overwrite:"
	sed 's/^/  - /' "$COLLISION_FILE" >&2
	exit 1
fi

log "Restoring all files tracked by ${CONFIG_BRANCH}..."
git restore \
	--source "${CONFIG_BRANCH}" \
	--worktree \
	--pathspec-from-file="$PATHSPEC_FILE"

git restore \
	--staged \
	--pathspec-from-file="$PATHSPEC_FILE" \
	2>/dev/null || true

ok "Overlay files restored and untracked in the current branch."

# Install the hook now that .githooks/ is available
install_hook

ok "Done."
