<img alt="Bunny CDN Logo" src="https://gist.githubusercontent.com/sifex/bb1ebae00c4c9a827a55a2b973fef0e7/raw/d79dab1b6959f580a3b7a2e6238dae7445203f2a/bunnycdn_logo.svg?sanitize=true" width="300" />

# Flysystem Adapter for BunnyCDN Storage

[![Build Status - Flysystem v1](https://img.shields.io/github/workflow/status/PlatformCommunity/flysystem-bunnycdn/build/v1?label=Flysystem%20v1&logo=github)](https://github.com/PlatformCommunity/flysystem-bunnycdn/actions) [![Build Status - Flysystem v1](https://img.shields.io/github/workflow/status/PlatformCommunity/flysystem-bunnycdn/build/v2?label=Flysystem%20v2&logo=github)](https://github.com/PlatformCommunity/flysystem-bunnycdn/actions) [![Build Status - Flysystem v1](https://img.shields.io/github/workflow/status/PlatformCommunity/flysystem-bunnycdn/build/v3?label=Flysystem%20v3&logo=github)](https://github.com/PlatformCommunity/flysystem-bunnycdn/actions) <br />[![Codecov](https://img.shields.io/codecov/c/github/PlatformCommunity/flysystem-bunnycdn)](https://codecov.io/gh/PlatformCommunity/flysystem-bunnycdn) [![Packagist Version](https://img.shields.io/packagist/v/platformcommunity/flysystem-bunnycdn)](https://packagist.org/packages/platformcommunity/flysystem-bunnycdn) ![Minimum PHP Version: 7.2](https://img.shields.io/badge/php-min%207.2-important) [![Licence: MIT](https://img.shields.io/packagist/l/platformcommunity/flysystem-bunnycdn)](https://github.com/PlatformCommunity/flysystem-bunnycdn/blob/master/LICENSE) [![Downloads](https://img.shields.io/packagist/dm/platformcommunity/flysystem-bunnycdn)](https://packagist.org/packages/platformcommunity/flysystem-bunnycdn)

## ⚠️ Note – Breaking Change 

> ⚠️ (21/Feb/22) As the upstream BunnyCDNStorage client has gone sometime without an update, it's become unsustainable to continue to use. For all updates, simply change the `BunnyCDNStorage` to `BunnyCDNClient` and re-import. ⚠️

## Installation

To install `flysystem-bunnycdn`, require the package with no version constraint. This should match the `flysystem-bunnycdn` version with your version of FlySystem (v1, v2, v3 etc).

```bash
composer require platformcommunity/flysystem-bunnycdn "*"
```

## Usage

```php
use BunnyCDN\Storage\BunnyCDNClient;
use League\Flysystem\Filesystem;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
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
use BunnyCDN\Storage\BunnyCDNClient;
use League\Flysystem\Filesystem;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion;

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

For a guide on how to use `flysystem-bunnycdn` in Laravel 9, follow the guide here:<br />
https://blog.sinn.io/bunny-net-php-flysystem-v3/#usage-in-laravel-9 

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
```

## Contributing

Pull requests welcome. Please feel free to lodge any issues as discussion points.

## Licence

The Flysystem adapter for Bunny.net is licensed under [MIT](https://github.com/PlatformCommunity/flysystem-bunnycdn/blob/master/LICENSE). 
