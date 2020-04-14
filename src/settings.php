<?php

define('QANB_DB_HOST', getenv('QANB_DB_HOST') !== false ? getenv('QANB_DB_HOST') : 'localhost');
define('QANB_DB_NAME', getenv('QANB_DB_NAME') !== false ? getenv('QANB_DB_NAME') : 'qanightlyresults');
define('QANB_DB_USERNAME', getenv('QANB_DB_USERNAME') !== false ? getenv('QANB_DB_USERNAME') : 'root');
define('QANB_DB_PASSWORD', getenv('QANB_DB_PASSWORD') !== false ? getenv('QANB_DB_PASSWORD') : '');
define('QANB_GCPURL', getenv('QANB_GCPURL') !== false ? getenv('QANB_GCPURL') : 'https://storage.googleapis.com/prestashop-core-nightly/');

define('QANB_VERSION', 1);
