#!/bin/bash

echo "Launching DB upgrade php script..." 

/usr/local/bin/php src/Upgrade/upgrade.php

exitCode=$?

echo "Script ended with this exit code: $exitCode"
