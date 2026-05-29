#!/usr/bin/env bash
# sync-dots.sh — Orphan overlay sync utility
#
# Checks out the latest files from config-assets into the working tree
# without tracking them in the current branch index.
#
# Usage: ./bin/sync-dots.sh

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
NC='\033[0m'

log()  { echo -e "${CYAN}[sync-dots]${NC} $*"; }
ok()   { echo -e "${GREEN}[sync-dots]${NC} $*"; }
err()  { echo -e "${RED}[sync-dots] ERROR:${NC} $*"; }

CONFIG_BRANCH="config-assets"
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

STASH_NEEDED=false
if ! git diff --quiet --ignore-submodules HEAD 2>/dev/null; then
	log "Uncommitted changes detected — stashing..."
	git stash push --include-untracked --message "sync-dots auto-stash $(date +%Y%m%d%H%M%S)"
	STASH_NEEDED=true
fi

log "Restoring overlay files from ${CONFIG_BRANCH}..."
git restore --source "${CONFIG_BRANCH}" --worktree -- \
	.agents/ \
	.codex/ \
	.gemini/ \
	.github/ \
	.history/ \
	.kilo/ \
	.letta/ \
	.playwright/ \
	.meta/ \
	.graphifyignore \
	graphify-out/ \
	AGENTS.md \
	GEMINI.md \
	.antigravitycli 2>/dev/null || true

git reset HEAD -- \
	.agents/ \
	.codex/ \
	.gemini/ \
	.github/ \
	.history/ \
	.kilo/ \
	.letta/ \
	.playwright/ \
	.meta/ \
	.graphifyignore \
	graphify-out/ \
	AGENTS.md \
	GEMINI.md \
	.antigravitycli 2>/dev/null || true

ok "Overlay files restored and untracked in the current branch."

if [[ "$STASH_NEEDED" = true ]]; then
	if git stash list | grep -q "sync-dots auto-stash"; then
		log "Restoring stashed changes..."
		git stash pop 2>/dev/null || true
	fi
fi

ok "Done."
