# QANightlyResults

QANightlyResults is a Symfony app, acting as a backend (via a JSON API) to handle and browse tests reports records.

You can use any frontend app you want to consume this API. We use a [Vue app](https://github.com/PrestaShop/nightly-board).

Its purpose is to :
1. store results of tests in a database
2. let people browse a report of a test execution
3. display some statistics about test failures

### Configuration

You can create a `.env.local` file at the root of the project. You can also pass the values via environment variables. 

Here are the main ones:

| Variables         |                                          |
|-------------------|------------------------------------------|
| DATABASE_URL      | DSN for MySQL Server                     |
| QANB_TOKEN        | Token to add JSON data through the Hook  |

## Usage

Install dependencies with a `composer install`.

Create a database with a `php bin/console doctrine:schema:update --dump-sql --force`.

## Web server configuration

Set up a vhost that points to the `/public` folder (example in the `vhost.conf` file).

## Inserting new data

Use the hook provided in the `Hook` controller. You need to call this URL: `BASE_URL/hook/reports/import` with the following GET 
parameters:
- `token`: the token set in the environment variable `QANB_TOKEN` (e.g.: `IpBzOmwXQUrW5Hn`)
- `filename` : the complete filename to look for in the Google Cloud Storage (e.g.: `2019-07-22-develop.json`). The 
name must follow this pattern: `/[0-9]{4}-[0-9]{2}-[0-9]{2}-(.*)?\.json/`

Optional:
- `force`: a special parameter used to force insert when a similar entry is found (criterias are :browser, campaign, date and version)
- `browser`: to specify the browser. Possible values are 'chromium' (default), 'firefox', and 'edge'.
- `campaign`: to specify the campaign. Possible values are 'functional' (default), 'sanity', 'e2e', and 'regression'.

EG : `api.mysite.com/hook/reports/import?token=IpBzOmwXQUrW5Hn&filename=2019-07-22-develop.json`

The files in the Google Cloud Storage might be huge, so be sure your server is properly configured to handle large files.

Files will be taken from `https://storage.googleapis.com/prestashop-core-nightly/reports/`.


## Containers

If you're working with docker, we have you covered.

```
docker-compose up -d
```

And you should have the application running locally.

Though, we're not providing any DB at this time, so you'll have to:

- Have your DB running 
- Edit the compose credentials about your DB
