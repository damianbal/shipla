# Shipla
> Create your API in no time!

Simple RESTful API platform which leys you create API just in seconds, note that it is limited, and should be only used for simple sites, where data is not that important, also it can be used by front-end developers which don't want to write their own API, or to test front-end, whatever you want just not something where data is important, Have fun!

You can also use Shipla which is hosted on heroku: https://shipla.herokuapp.com/

## Installation

```sh
composer install
php artisan migrate:install
php artisan migrate
php artisan passport:install
php artisan serve
```

Of course you need to create .env file based on .env.example and provide your database info

## Usage example

See routes/api.php yourself, it is not that complicated.

## Development setup

Well, you would need a MySQL server installed, and PHP version which will satify Laravel (PHP 7), and that's it!

## Release History

* 0.1.0
    * First release

## Meta

Damian Balandowski – balandowski@icloud.com

[https://github.com/damianbal](https://github.com/damianbal)

## Contributing

1. Fork it (<https://github.com/damianbal/shipla/fork>)
2. Create your feature branch (`git checkout -b feature/fooBar`)
3. Commit your changes (`git commit -am 'Add some fooBar'`)
4. Push to the branch (`git push origin feature/fooBar`)
5. Create a new Pull Request
