<img alt="Bunny CDN Logo" src="https://gist.githubusercontent.com/sifex/bb1ebae00c4c9a827a55a2b973fef0e7/raw/d79dab1b6959f580a3b7a2e6238dae7445203f2a/bunnycdn_logo.svg?sanitize=true" width="300" />

# Flysystem Adapter for BunnyCDN Storage

[![Build Status](https://github.com/PlatformCommunity/flysystem-bunnycdn/actions/workflows/php.yml/badge.svg)](https://github.com/PlatformCommunity/flysystem-bunnycdn/actions) [![Codecov](https://img.shields.io/codecov/c/github/PlatformCommunity/flysystem-bunnycdn)](https://codecov.io/gh/PlatformCommunity/flysystem-bunnycdn) [![Packagist Version](https://img.shields.io/packagist/v/platformcommunity/flysystem-bunnycdn)](https://packagist.org/packages/platformcommunity/flysystem-bunnycdn) [![Packagist](https://img.shields.io/packagist/l/platformcommunity/flysystem-bunnycdn)](https://github.com/PlatformCommunity/flysystem-bunnycdn/blob/master/LICENSE) [![Packagist](https://img.shields.io/packagist/dm/platformcommunity/flysystem-bunnycdn)](https://packagist.org/packages/platformcommunity/flysystem-bunnycdn)

## Installation

```bash
composer require platformcommunity/flysystem-bunnycdn
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


## Contributing

Pull requests welcome. Please feel free to lodge any issues as discussion points.
 
## Licence

The Flysystem adapter for BunnyCDN is licensed under [MIT](https://github.com/PlatformCommunity/flysystem-bunnycdn/blob/master/LICENSE). 
