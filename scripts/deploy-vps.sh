#!/usr/bin/env bash
set -euo pipefail
repo=/home/sigma-eg/git/sigma-site.git
target=${1:-$(git --git-dir="$repo" rev-parse refs/heads/main)}
runner="$repo/source-runner-$target.py"
git --git-dir="$repo" show "$target:scripts/vps/deploy-source.py" > "$runner"
chmod 600 "$runner"
exec python3 "$runner" "$target"
