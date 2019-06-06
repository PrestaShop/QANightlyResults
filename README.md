# CIPSJsonParser

CIPSJsonParser is a small PHP script based on CodeIgniter. Its purpose is to :
1. store results of tests in a database
2. let people browse a report of a test execution
3. display some statistics about test failures

## Usage

Create a database following the schema provided in schema.sql at the root of the project. Be sure to update config files in CI config/ directory.

Use insert.php to insert json files. Be sure to edit connection info at the top of the file. The first argument of the script is the path to the file. The filename **must** look like this : reports_2019-06-05-1.7.6.x.json ("reports_YYYY-MM-DD-VERSION.json") to work properly.
