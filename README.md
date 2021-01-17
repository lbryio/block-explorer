# LBRYexplorer
A [LBRY](https://lbry.com) block explorer based on Laravel.

#### Dependencies:
* [PHP 7.4.11]
* [Laravel v8.16.1](https://laravel.com/docs/8.x)

### Install

* `git clone https://github.com/marcdeb1/LBRYEXPLORER.git`
* Install dependencies with `composer update`
* Create .env file from .env.example and edit variables according to your environment

### Run

#### Run with artisan
* Launch server with `php artisan serve`
* Open your browser at http://localhost:8000

#### Run with Docker
* `docker build -t lbry-explorer .`
* `docker-compose up`
* Open your browser at http://localhost

#### Database:
The LBRY Explorer is using [LBRY Chainquery v1.8.1](https://github.com/lbryio/chainquery/releases/tag/v1.8.1) as a remote database.

Current model schema reflects chainquery [schema](https://github.com/lbryio/chainquery/blob/master/db/chainquery_schema.sql)

![Model Schema](https://spee.ch/@SK3LA:3/chainqueryschema2.svg)
