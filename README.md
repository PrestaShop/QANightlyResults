# CIPSJsonParser

CIPSJsonParser is a small PHP script based on CodeIgniter. Its purpose is to :
1. store results of tests in a database
2. let people browse a report of a test execution
3. display some statistics about test failures

## Usage

Create a database following the schema provided in schema.sql at the root of the project.

You can then edit the `config.php` and `database.php` files in the `config/` folder. You can also pass the values via environment variables. Here are the main ones:

|Variable           |   |
|-------------------|---|
| QANB_BASEURL      | Base URL of the application, eg https://qaboard.xxx.com |
| QANB_DB_HOST      | Database host address  |
| QANB_DB_USERNAME  | Database username  |
| QANB_DB_PASSWORD  | Database password  |
| QANB_DB_NAME      | Database name  |


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

Use `insert.php` to insert json files. This file uses the `database.php` config file in `application/config` so be sure it's set up correctly.
 
The first argument of the script is the path to the file you want to insert. The second argument is the version. Example:

```
php insert.php application/files/reports_2019-06-18_develop.json develop
```

## Containers

If you're working with docker, we have you covered.

```
docker-compose up -d
```

And you should have the application running locally.

Though, we're not providing any DB at this time, so you'll have to:

- Have your DB running 
- Edit the compose credentials about your DB
