#!/bin/sh

set -eu

version=${1:-}
if [ "$#" -ne 1 ] || ! printf '%s\n' "$version" | grep -Eq '^[0-9]+[.][0-9]+[.][0-9]+$'; then
    echo "Usage: $0 MAJEUR.MINEUR.CORRECTIF" >&2
    exit 2
fi

printf '%s\n' "$version" >version.txt
sed -i.bak "s/^    app[.]version: '.*' # x-release-version$/    app.version: '$version' # x-release-version/" app/config/services.yaml
rm -f app/config/services.yaml.bak
grep -Fqx "    app.version: '$version' # x-release-version" app/config/services.yaml
