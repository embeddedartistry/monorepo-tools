#!/bin/bash
#
# Wrapper script to run git-filter-repo with signature preservation
#
# This script automates the process of:
# 1. Storing signatures in commit messages
# 2. Running git-filter-repo
# 3. Restoring signatures from commit messages
#
# Usage: filter_repo_with_signatures.sh <git-filter-repo-args>
#
# Example:
#   filter_repo_with_signatures.sh --subdirectory-filter packages/mypackage --force
#

set -e

SCRIPT_DIR=$(dirname "$0")
STORE_SIG="$SCRIPT_DIR/store_signatures.py"
RESTORE_SIG="$SCRIPT_DIR/restore_signatures.py"

# Check if scripts exist
if [ ! -f "$STORE_SIG" ]; then
    echo "Error: store_signatures.py not found at $STORE_SIG"
    exit 1
fi

if [ ! -f "$RESTORE_SIG" ]; then
    echo "Error: restore_signatures.py not found at $RESTORE_SIG"
    exit 1
fi

# Check if git-filter-repo is available
if ! command -v git-filter-repo &> /dev/null; then
    echo "Error: git-filter-repo not found in PATH"
    exit 1
fi

# Check if we're in a git repository
if ! git rev-parse --git-dir > /dev/null 2>&1; then
    echo "Error: Not in a git repository"
    exit 1
fi

echo "=== Step 1: Storing signatures in commit messages ==="
python3 "$STORE_SIG" -v
echo

echo "=== Step 2: Running git-filter-repo ==="
git-filter-repo "$@"
echo

echo "=== Step 3: Restoring signatures from commit messages ==="
python3 "$RESTORE_SIG" -v
echo

echo "=== Complete! ==="
echo "Signatures have been preserved through the filtering process."
echo "Note: Signatures may not verify if commit content changed."
