#!/bin/sh
#

# (1) change working directory
SCRIPT=$(readlink -e "$0")
echo "SCRIPT = $SCRIPT"
DIR=$(dirname "$SCRIPT")
echo "1. DIR = $DIR"

while [ 1 ]; do
   [ -d "$DIR/.git" ] && break
   [ $DIR == "/"    ] && echo "error: .git directory not found" && exit 1
   DIR=$(dirname "$DIR")
   echo "n. DIR = $DIR"
done


# (2) update project
echo Updating "$DIR"...

BRANCH=$(git rev-parse --abbrev-ref HEAD)

git fetch origin                                                                  || exit
git --no-pager status                                                             || exit
git --no-pager diff --stat --ignore-space-at-eol HEAD origin/$BRANCH              || exit
git reset --hard origin/$BRANCH                                                   || exit
git --no-pager status                                                             || exit


# (3) check/update additional requirements: dependencies, submodules, file permissions
