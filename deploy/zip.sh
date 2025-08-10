#!/bin/sh

cd "$(dirname "$0")"

tar -czvf deploy.tar.gz --transform='s|^|deploy/|' prod easy VERSION.txt