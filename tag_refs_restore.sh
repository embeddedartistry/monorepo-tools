#!/bin/sh

# Move tag refs from refs/original-tags/ into refs/tags/
#
# Usage: tag_refs_restore.sh

for TAG_REF in $(git for-each-ref --format="%(refname)" refs/tags/); do
    git update-ref $TAG_REF refs/original-tags/$TAG_REF
done
