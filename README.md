# LBRY Block Explorer

A simple PHP block explorer for browsing transactions and claims on the [LBRY](https://lbry.com) blockchain. The explorer was developed using CakePHP which is a model-view-controller (MVC) PHP framework.

## Installation
There are some prerequisites that need to be installed before the explorer can be accessed.
* Web server - Apache, caddy or nginx
* [lbrycrd](https://github.com/lbryio/lbrycrd) with txindex turned on
* [Python claims decoder](https://github.com/cryptodevorg/lbry-decoder)
* MariaDB 10.2 or higher
* Redis Server (optional, only required for the CakePHP redis cache engine, or to run `forevermempool`)
* PHP 7.2 or higher
  * php-fpm
  * [igbinary extension](https://github.com/igbinary/igbinary)
  * [phpredis extension](https://github.com/phpredis/phpredis)
* composer (PHP package manager)

### Installation steps
* Clone the Github repository. `git clone https://github.com/lbryio/block-explorer`
* Create a MariaDB database using the DDL found in `block-explorer/sql/lbryexplorer.ddl.sql`
* Change the working directory to the cloned directory and run composer.
```
cd block-explorer
composer update
```
* Create the directories, `tmp` and `logs` in the `block-explorer` folder if they have not been created yet, and make sure that they are writable by the web server.
* Copy `config/app.default.php` to `config/app.php`. Edit the database connection values to correspond to your environment.
* Copy `config/lbry.default.php` to `config/lbry.php`. Update the values for LBRY RPC URL and the Redis URL to correspond to your environment.
* Configure your web server with the host root folder set to `<path to>/block-explorer/webroot` where `<path to>` is the absolute path to the configuration. Here is a sample nginx configuration. You can make changes to this configuration to correspond to your environment.
```
server {
    listen        80;
    server_name   my.explorer.com;

    root /var/www/block-explorer/webroot;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    # pass the PHP scripts to FastCGI server listening on the php-fpm socket
    location ~ \.php$ {
        try_files $uri =404;
        include /etc/nginx/fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_ignore_client_abort on;
        fastcgi_param PHP_AUTH_USER $remote_user;
        fastcgi_param PHP_AUTH_PW $http_authorization;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```
* Restart your web server.


### Cron jobs
There are a few scripts which can be set up as cron jobs or scheduled tasks.

#### blocks.sh
Detect new LBRY blocks. Can also be configured to be triggered using the lbrycrd `blocknotify` flag. This cron will create new blocks obtained from lbrycrd starting from the highest block number in the database, and then create the corresponding block transactions. If there are pending transactions created by the forevermempool script, they will be automatically associated with the respective blocks.

#### claimindex.sh
Create claims found on the LBRY blockchain in the database. This requires the Python decoder to be running in the background.

#### pricehistory.sh
Get the current LBC price in USD and store the value in the `PriceHistory` table. This also caches the most recent price in Redis.

#### forever.sh
Run the `forevermempool` script, and restart if necessary. The `forevermempool` script checks the LBRY blockchain mempool every second and creates transactions found in the database. The script makes use of Redis for caching the pending transaction IDs.


## Usage
Launch the URL for the configured web server root in a browser.


## Contributing
Contributions to this project are welcome, encouraged, and compensated. For more details, see https://lbry.tech/contribute


## License
This project is MIT licensed. For the full license, see [LICENSE](LICENSE).


## Security
We take security seriously. Please contact security@lbry.io regarding any security issues. Our PGP key is [here](https://keybase.io/lbry/key.asc) if you need it.


## Contact
The primary contact for this project is [@akinwale](https://github.com/akinwale) (akinwale@lbry.com)
