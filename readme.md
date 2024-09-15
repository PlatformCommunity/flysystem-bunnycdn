<img alt="Bunny CDN Logo" src="https://gist.githubusercontent.com/sifex/bb1ebae00c4c9a827a55a2b973fef0e7/raw/d79dab1b6959f580a3b7a2e6238dae7445203f2a/bunnycdn_logo.svg?sanitize=true" width="300" />

# Flysystem Adapter for BunnyCDN Storage
[![Build Status - Flysystem v2](https://img.shields.io/github/actions/workflow/status/PlatformCommunity/flysystem-bunnycdn/php.yml?branch=v2&label=Flysystem%20v2)](https://github.com/PlatformCommunity/flysystem-bunnycdn/actions) [![Build Status - Flysystem v3](https://img.shields.io/github/actions/workflow/status/PlatformCommunity/flysystem-bunnycdn/php.yml?branch=v3&label=Flysystem%20v3)](https://github.com/PlatformCommunity/flysystem-bunnycdn/actions) <br />[![Codecov](https://img.shields.io/codecov/c/github/PlatformCommunity/flysystem-bunnycdn)](https://codecov.io/gh/PlatformCommunity/flysystem-bunnycdn) [![Packagist Version](https://img.shields.io/packagist/v/platformcommunity/flysystem-bunnycdn)](https://packagist.org/packages/platformcommunity/flysystem-bunnycdn) ![Minimum PHP Version: 7.4](https://img.shields.io/badge/php-min%207.4-important) [![Licence: MIT](https://img.shields.io/packagist/l/platformcommunity/flysystem-bunnycdn)](https://github.com/PlatformCommunity/flysystem-bunnycdn/blob/master/LICENSE) [![Downloads](https://img.shields.io/packagist/dm/platformcommunity/flysystem-bunnycdn)](https://packagist.org/packages/platformcommunity/flysystem-bunnycdn)


## Installation

To install `flysystem-bunnycdn`, require the package with no version constraint. This should match the `flysystem-bunnycdn` version with your version of FlySystem (v2, v3 etc).

```bash
composer require platformcommunity/flysystem-bunnycdn "*"
```

## Usage

```php
use League\Flysystem\Filesystem;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion;

$adapter = new BunnyCDNAdapter(
    new BunnyCDNClient(
        'storage-zone', 
        'api-key', 
        BunnyCDNRegion::FALKENSTEIN
    )
);

$filesystem = new Filesystem($adapter);
```

### Usage with Pull Zones

To have BunnyCDN adapter publish to a public CDN location, you have to a "Pull Zone" connected to your BunnyCDN Storage Zone. Add the full URL prefix of your Pull Zone (including `http://`/`https://`) to the BunnyCDNAdapter parameter like shown below.


```php
$adapter = new BunnyCDNAdapter(
    new BunnyCDNClient(
        'storage-zone',
        'api-key',
        BunnyCDNRegion::FALKENSTEIN
    ),
    'https://testing.b-cdn.net/' # Pull Zone URL
);
$filesystem = new Filesystem($adapter);
```

_Note: You can also use your own domain name if it's configured in the pull zone._

Once you add your pull zone, you can use the `->getUrl($path)`, or in Laravel, the `->url($path)` command to get the fully qualified public URL of your BunnyCDN assets.

## Usage in Laravel 9
To add BunnyCDN adapter as a custom storage adapter in Laravel 9, install using the `v3` composer installer.

```bash
composer require platformcommunity/flysystem-bunnycdn "^3.0"
```

Next, install the adapter to your `AppServiceProvider` to give Laravel's FileSystem knowledge of the BunnyCDN adapter.

```php
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('bunnycdn', function ($app, $config) {
            $adapter = new BunnyCDNAdapter(
                new BunnyCDNClient(
                    $config['storage_zone'],
                    $config['api_key'],
                    $config['region']
                ),
                $config['pull_zone']
            );

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });
    }
```

Finally, add the `bunnycdn` driver into your `config/filesystems.php` configuration file.

```php
        ... 
        
        'bunnycdn' => [
            'driver' => 'bunnycdn',
            'storage_zone' => env('BUNNYCDN_STORAGE_ZONE'),
            'pull_zone' => env('BUNNYCDN_PULL_ZONE'),
            'api_key' => env('BUNNYCDN_API_KEY'),
            'region' => env('BUNNYCDN_REGION', \PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion::DEFAULT)
        ],
        
        ...
```

Lastly, populate your `BUNNYCDN_STORAGE_ZONE`, `BUNNYCDN_API_KEY` `BUNNYCDN_REGION` variables in your `.env` file.

```dotenv
BUNNYCDN_STORAGE_ZONE=testing_storage_zone
BUNNYCDN_PULL_ZONE=https://testing.b-cdn.net
BUNNYCDN_API_KEY="api-key"
# BUNNYCDN_REGION=uk
```

After that, you can use the `bunnycdn` disk in Laravel 9.

```php
Storage::disk('bunnycdn')->put('index.html', '<html>Hello World</html>');

return response(Storage::disk('bunnycdn')->get('index.html'));
```

_Note: You may have to run `php artisan config:clear` in order for your configuration to be refreshed if your app is running with a config cache driver / production mode._

## Regions

For a full region list, please visit the [BunnyCDN API documentation page](https://docs.bunny.net/reference/storage-api#storage-endpoints).

`flysystem-bunnycdn` also comes with constants for each region located within `PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion`.

### List of Regions

```php
# Europe
BunnyCDNRegion::FALKENSTEIN = 'de';
BunnyCDNRegion::STOCKHOLM = 'se';

# United Kingdom
BunnyCDNRegion::UNITED_KINGDOM = 'uk';

# USA
BunnyCDNRegion::NEW_YORK = 'ny';
BunnyCDNRegion::LOS_ANGELAS = 'la';

# SEA
BunnyCDNRegion::SINGAPORE = 'sg';

# Oceania
BunnyCDNRegion::SYDNEY = 'syd';

# Africa
BunnyCDNRegion::JOHANNESBURG = 'jh';

# South America
BunnyCDNRegion::BRAZIL = 'br';
```

## Contributing

Pull requests welcome. Please feel free to lodge any issues as discussion points.

## Development

Most of the understanding of how the Flysystem Adapter for BunnyCDN works comes from `tests/`. If you want to complete tests against a live BunnyCDN endpoint, copy the `tests/ClientDI_Example.php` to `tests/ClientDI.php` and insert your credentials into there. You can then run the whole suite by running `vendor/bin/phpunit`, or against a specific file by running `vendor/bin/phpunit --bootstrap tests/ClientDI.php tests/ClientTest.php`.


## Licence

The Flysystem adapter for Bunny.net is licensed under [MIT](https://github.com/PlatformCommunity/flysystem-bunnycdn/blob/master/LICENSE). 
