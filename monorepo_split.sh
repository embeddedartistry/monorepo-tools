#!/bin/sh

# Split monorepo and push all main branches and all tags into specified remotes
# You must first build the monorepo via "monorepo_build" (uses same parameters as "monorepo_split")
#
# If subdirectory is not specified remote name will be used instead.
#
# You can also override the branch to be merged in, if main is not appropriate.
# But you must specify a directory for this to work (this can match the remote name if this is the
# desired behavior).
#
# GPG Signature Preservation:
# This script automatically preserves GPG commit signatures during the split process by:
# 1. Storing signatures in commit messages before filtering (store_signatures.py)
# 2. Restoring signatures from messages after filtering (restore_signatures.py)
# Requires Python 3.6+ and Git 2.34+ for signature support.
#
# Usage: monorepo_split.sh <remote-name>[:<subdirectory>][:<branch>] <remote-name>[:<subdirectory>][:<branch>] ...
#
# Example: monorepo_split.sh main-repository package-alpha:packages/alpha:master \
#           package-beta:packages/beta:production

# Check provided arguments
if [ "$#" -lt "1" ]; then
    echo 'Please provide at least 1 remote for splitting'
    echo 'Usage: monorepo_split.sh <remote-name>[:<subdirectory>] <remote-name>[:<subdirectory>] ...'
    echo 'Example: monorepo_split.sh main-repository package-alpha:packages/alpha package-beta:packages/beta'
    exit
fi
# Get directory of the other scripts
MONOREPO_SCRIPT_DIR=$(dirname "$0")

git branch main-checkpoint

# Capture remote URLs before git-filter-repo removes them
declare -A REMOTE_URLS
for PARAM in $@; do
    PARAM_ARR=(${PARAM//:/ })
    REMOTE=${PARAM_ARR[0]}
    REMOTE_URL=$(git remote get-url $REMOTE 2>/dev/null)
    if [ $? -eq 0 ]; then
        REMOTE_URLS[$REMOTE]=$REMOTE_URL
        echo "Captured remote URL for '$REMOTE': $REMOTE_URL"
    else
        echo "Warning: Remote '$REMOTE' does not exist. Skipping."
    fi
done

for PARAM in $@; do
    # Parse parameters in format <remote-name>[:<subdirectory>]
    PARAM_ARR=(${PARAM//:/ })
    REMOTE=${PARAM_ARR[0]}
    SUBDIRECTORY=${PARAM_ARR[1]}
    if [ "$SUBDIRECTORY" == "" ]; then
        SUBDIRECTORY=$REMOTE
    fi
    BRANCH_TO_MERGE=${PARAM_ARR[2]}
    if [ "$BRANCH_TO_MERGE" == "" ]; then
        BRANCH_TO_MERGE=main
    fi

    # Rewrite git history of main branch
    echo "Splitting repository for the remote '$REMOTE' from subdirectory $SUBDIRECTORY"
    git checkout main

    # Store GPG signatures in commit messages before filtering
    echo "Storing GPG signatures in commit messages..."
    python3 $MONOREPO_SCRIPT_DIR/store_signatures.py --refs main $(git tag -l)
    if [ $? -ne 0 ]; then
        echo "Warning: Failed to store signatures, continuing anyway..."
    fi

    $MONOREPO_SCRIPT_DIR/rewrite_history_from.sh $SUBDIRECTORY main $(git tag -l)
    if [ $? -eq 0 ]; then
        # Restore GPG signatures from commit messages after filtering
        echo "Restoring GPG signatures from commit messages..."
        python3 $MONOREPO_SCRIPT_DIR/restore_signatures.py --refs main $(git tag -l)
        if [ $? -ne 0 ]; then
            echo "Warning: Failed to restore signatures, continuing anyway..."
        fi

        # Restore remote URL after git-filter-repo removed it
        if [ ! -z "${REMOTE_URLS[$REMOTE]}" ]; then
            git remote remove $REMOTE 2>/dev/null
            git remote add $REMOTE "${REMOTE_URLS[$REMOTE]}"
            echo "Restored remote '$REMOTE' with URL: ${REMOTE_URLS[$REMOTE]}"
        fi

        echo "Pushing changes made on 'main' and all tags into '$REMOTE/$BRANCH_TO_MERGE'"
        git push --tags $REMOTE main:$BRANCH_TO_MERGE
        git lfs push $REMOTE main:$BRANCH_TO_MERGE
    else
        echo "Splitting repository for the remote '$REMOTE' failed! Not pushing anything into it."
    fi
    git reset main-checkpoint --hard
    $MONOREPO_SCRIPT_DIR/tag_refs_restore.sh
done

git branch -d main-checkpoint
