#!/bin/sh

# Rewrite git history so that all filepaths are in a specific subdirectory
# You can use arguments for "git rev-list" to specify what commits to rewrite (defaults to rewriting history of the checked-out branch)
# All tags in the provided range will be rewritten as well
#
# Usage: rewrite_history_into.sh <subdirectory> [<rev-list-args>]
#
# Example: rewrite_history_into.sh packages/alpha
# Example: rewrite_history_into.sh main-repository --branches

SUBDIRECTORY=$1
REV_LIST_PARAMS=${@:2}
echo "Rewriting history into a subdirectory '$SUBDIRECTORY'"
# All paths in the index are prefixed with a subdirectory and the index is updated
# Previous index file is replaced by a new one (otherwise each file would be in the index twice)
git filter-repo --to-subdirectory-filter $SUBDIRECTORY --force
