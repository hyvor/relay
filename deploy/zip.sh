#!/bin/sh

cd "$(dirname "$0")" || exit

# replace hyvor/relay:latest with hyvor/relay:<version> in compose files
if [ -f VERSION.txt ]; then
    VERSION=$(cat VERSION.txt)
    sed -i "s|hyvor/relay:latest|hyvor/relay:$VERSION|g" prod/compose.yaml easy/compose.yaml
fi

tar -czvf deploy.tar.gz --transform='s|^|deploy/|' prod easy VERSION.txt