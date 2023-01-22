#!/bin/sh

# Split monorepo and push all main branches and all tags into specified remotes
# You must first build the monorepo via "monorepo_build" (uses same parameters as "monorepo_split")
# If subdirectory is not specified remote name will be used instead
#
# Usage: monorepo_split.sh <remote-name>[:<subdirectory>] <remote-name>[:<subdirectory>] ...
#
# Example: monorepo_split.sh main-repository package-alpha:packages/alpha package-beta:packages/beta

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

for PARAM in $@; do
    # Parse parameters in format <remote-name>[:<subdirectory>]
    PARAM_ARR=(${PARAM//:/ })
    REMOTE=${PARAM_ARR[0]}
    SUBDIRECTORY=${PARAM_ARR[1]}
    if [ "$SUBDIRECTORY" == "" ]; then
        SUBDIRECTORY=$REMOTE
    fi
    # Rewrite git history of main branch
    echo "Splitting repository for the remote '$REMOTE' from subdirectory $SUBDIRECTORY"
    git checkout main
    $MONOREPO_SCRIPT_DIR/rewrite_history_from.sh $SUBDIRECTORY main $(git tag -l)
    if [ $? -eq 0 ]; then
        echo "Pushing branch 'main' and all tags into '$REMOTE'"
        git push --tags $REMOTE main
        git lfs push $REMOTE main
    else
        echo "Splitting repository for the remote '$REMOTE' failed! Not pushing anything into it."
    fi
    git reset main-checkpoint --hard
    $MONOREPO_SCRIPT_DIR/tag_refs_restore.sh
done

git branch -d main-checkpoint

