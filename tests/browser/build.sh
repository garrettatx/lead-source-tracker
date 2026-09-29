#!/bin/sh
# Builds fixture.html from the plugin's real footer output. Serve this folder and open
# http://localhost:8765/fixture.html?utm_source=google&utm_medium=cpc&utm_campaign=remodels&gclid=Cj0KCQjw_e2etest01
set -e
cd "$(dirname "$0")"
{ cat page-top.html; GD_LS_REFRESH=0 php ../wp-harness.php footer ../fixtures/site-config.php; cat page-checks.html; } > fixture.html
echo "built $(pwd)/fixture.html"
