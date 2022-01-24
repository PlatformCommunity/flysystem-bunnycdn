<img alt="Bunny CDN Logo" src="https://gist.githubusercontent.com/sifex/bb1ebae00c4c9a827a55a2b973fef0e7/raw/d79dab1b6959f580a3b7a2e6238dae7445203f2a/bunnycdn_logo.svg?sanitize=true" width="300" />

# Flysystem Adapter for BunnyCDN Storage

[![Build Status - Flysystem v1](https://img.shields.io/github/workflow/status/PlatformCommunity/flysystem-bunnycdn/build/v1?label=Flysystem%20v1&logo=github)](https://github.com/PlatformCommunity/flysystem-bunnycdn/actions) [![Build Status - Flysystem v1](https://img.shields.io/github/workflow/status/PlatformCommunity/flysystem-bunnycdn/build/v2?label=Flysystem%20v2&logo=github)](https://github.com/PlatformCommunity/flysystem-bunnycdn/actions) [![Codecov](https://img.shields.io/codecov/c/github/PlatformCommunity/flysystem-bunnycdn)](https://codecov.io/gh/PlatformCommunity/flysystem-bunnycdn) [![Packagist Version](https://img.shields.io/packagist/v/platformcommunity/flysystem-bunnycdn)](https://packagist.org/packages/platformcommunity/flysystem-bunnycdn) ![Minimum PHP Version: 7.2](https://img.shields.io/badge/php-min%207.2-important) [![Licence: MIT](https://img.shields.io/packagist/l/platformcommunity/flysystem-bunnycdn)](https://github.com/PlatformCommunity/flysystem-bunnycdn/blob/master/LICENSE) [![Downloads](https://img.shields.io/packagist/dm/platformcommunity/flysystem-bunnycdn)](https://packagist.org/packages/platformcommunity/flysystem-bunnycdn)

## Installation

For **Flysystem v1**, use the v1 version of `flysystem-bunnycdn`.

```bash
composer require platformcommunity/flysystem-bunnycdn "^1.0"
```

For **Flysystem v2**, use the v2 version of `flysystem-bunnycdn`.

```bash
composer require platformcommunity/flysystem-bunnycdn "^2.0"
```


## Usage

```php
use BunnyCDN\Storage\BunnyCDNStorage;
use League\Flysystem\Filesystem;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;

$client = new BunnyCDNAdapter(new BunnyCDNStorage('storage-zone', 'api-key', 'de'));
$filesystem = new Filesystem($client);
```

### Usage with Pull Zones

To have BunnyCDN adapter publish to a public CDN location, you have to a "Pull Zone" connected to your BunnyCDN Storage Zone. Add the full URL prefix of your Pull Zone (including `http://`/`https://`) to the BunnyCDNAdapter parameter like shown below.


```php
use BunnyCDN\Storage\BunnyCDNStorage;
use League\Flysystem\Filesystem;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;

$client = new BunnyCDNAdapter(
    new BunnyCDNStorage('storage-zone', 'api-key', 'de'),
    'https://testing.b-cdn.net/'
);
$filesystem = new Filesystem($client);
```

_Note: You can also use your own domain name if it's configured in the storage zone._

Once you add your pull zone, you can use the `->getUrl($path)`, or in Laravel, the `->url($path)` command to get the fully qualified public URL of your BunnyCDN assets.

## Usage in Laravel

To add BunnyCDN adapter as a custom storage adapter, install using the `v1` composer installer.

```bash
composer require platformcommunity/flysystem-bunnycdn "^1.0"
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
            $client = new BunnyCDNAdapter(
                new BunnyCDNStorage(
                    $config['storage_zone'],
                    $config['api_key'],
                    $config['region']
                ),
                'https://testing.b-cdn.net' # Pull Zone URL (optional)
            );

            return new Filesystem($client);
        });
    }
```

Finally, add the `bunnycdn` driver into your `config/filesystems.php` configuration file.

```php
        ... 
        
        'bunnycdn' => [
            'driver' => 'bunnycdn',
            'storage_zone' => env('BUNNYCDN_STORAGE_ZONE'),
            'api_key' => env('BUNNYCDN_APY_KEY'),
            'region' => env('BUNNYCDN_REGION')
        ],
        
        ...
```

After populating your `BUNNYCDN_STORAGE_ZONE`, `BUNNYCDN_APY_KEY` `BUNNYCDN_REGION` variables in your `.env` file, you can then use the Storage facade with BunnyCDN.

```php
Storage::disk('bunnycdn')->put('index.html', '<html>Hello World</html>');

return response(Storage::disk('bunnycdn')->get('index.html'));
```

_Note: You may have to run `php artisan config:clear` in order for your configuration to be refreshed if your app is running with a config cache driver / production mode._

## Regions

For a full region list, please visit the [BunnyCDN API documentation page](https://docs.bunny.net/reference/storage-api#storage-endpoints).

## Contributing

Pull requests welcome. Please feel free to lodge any issues as discussion points.

## Licence

The Flysystem adapter for BunnyCDN is licensed under [MIT](https://github.com/PlatformCommunity/flysystem-bunnycdn/blob/master/LICENSE). 
