# QANightlyResults

QANightlyResults is a small PHP script based on CodeIgniter. Its purpose is to :
1. store results of tests in a database
2. let people browse a report of a test execution
3. display some statistics about test failures

## Usage

Create a database following the schema provided in schema.sql at the root of the project.

You can then edit the `config.php` and `database.php` files in the `config/` folder. You can also pass the values via environment variables. Here are the main ones:

|Variables           |   |
|-------------------|---|
| QANB_BASEURL      | Base URL of the application, eg https://qaboard.xxx.com |
| QANB_DB_HOST      | Database host address  |
| QANB_DB_USERNAME  | Database username  |
| QANB_DB_PASSWORD  | Database password  |
| QANB_DB_NAME      | Database name  |
| QANB_GA_KEY       | Google Analytics Key (optional)  |
| QANB_TOKEN        | Token to add JSON data through the Hook  |
| QANB_GCPURL       | URL the the GCP repository (must ends with a `/`)  |


## Web server configuration

Set up a vhost that points to the `/public` folder:

```
<VirtualHost *:80>
    DocumentRoot "/PATH/TO/PUBLIC/FOLDER"
    ServerName www.url.dev
    ServerAlias url.dev

   <Directory "/PATH/TO/PUBLIC/FOLDER/">
        Options FollowSymLinks Indexes MultiViews
        AllowOverride All
        SetEnv CI_ENV "production"
        Order allow,deny
        Allow from all
   </Directory>
</VirtualHost>
```

## Inserting new data

Use the hook provided in the `Hook` controller. You need to call this URL: `BASE_URL/hook/add` with the following GET parameters:
- `token`: the token set in the environment variable `QANB_TOKEN` (e.g.: `IpBzOmwXQUrW5Hn`)
- `filename` : the complete filename to look for in the Google Cloud Storage (e.g.: `2019-07-22-develop.json`). The name must follow this pattern: `/[0-9]{4}-[0-9]{2}-[0-9]{2}-(.*)?\.json/`
Optional:
- `force`: a special parameter used to force insert when a similar entry is found (criterias are date and version)

EG : `mysite.com/hook/add?token=IpBzOmwXQUrW5Hn&filename=2019-07-22-develop.json`

The files in the Google Cloud Storage might be huge, so be sure your server is properly configured to handle large files (~ 10Mio).

Files will be taken from https://storage.googleapis.com/prestashop-core-nightly/reports/ (unless specified otherwise in the environment variable `QANB_GCPURL`).


## Containers

If you're working with docker, we have you covered.

```
docker-compose up -d
```

And you should have the application running locally.

Though, we're not providing any DB at this time, so you'll have to:

- Have your DB running 
- Edit the compose credentials about your DB
