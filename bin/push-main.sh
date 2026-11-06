#!/usr/bin/env bash
# Pushes HEAD to main for the workflows that commit by themselves (readme-screenshots.yml, update-cjxl.yml). Everyone
# else goes through a pull request: only a deploy key can skip that rule, and this one is the MAIN_PUSH_KEY secret of
# the repo-bot environment, which only workflows running on main can read (README, Deploy).
set -euo pipefail
: "${MAIN_PUSH_KEY:?Set MAIN_PUSH_KEY to the deploy key}"

SSH=$(mktemp -d)
trap 'rm -rf "$SSH"' EXIT
printf '%s\n' "$MAIN_PUSH_KEY" > "$SSH/key"
chmod 600 "$SSH/key"
# GitHub's published key (https://api.github.com/meta), so nothing else can pose as it
echo 'github.com ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOMqqnkVzrm0SdG6UOoqKLsabgH5C9okWi0dh2l9GKJl' > "$SSH/known_hosts"
GIT_SSH_COMMAND="ssh -i $SSH/key -o IdentitiesOnly=yes -o UserKnownHostsFile=$SSH/known_hosts -o StrictHostKeyChecking=yes" \
    git push --quiet "git@github.com:$GITHUB_REPOSITORY.git" HEAD:main
