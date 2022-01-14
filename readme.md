<img alt="Bunny CDN Logo" src="https://gist.githubusercontent.com/sifex/bb1ebae00c4c9a827a55a2b973fef0e7/raw/d79dab1b6959f580a3b7a2e6238dae7445203f2a/bunnycdn_logo.svg?sanitize=true" width="300" />

# Flysystem Adapter for BunnyCDN Storage

[![Build Status](https://github.com/PlatformCommunity/flysystem-bunnycdn/actions/workflows/php.yml/badge.svg)](https://github.com/PlatformCommunity/flysystem-bunnycdn/actions) [![Codecov](https://img.shields.io/codecov/c/github/PlatformCommunity/flysystem-bunnycdn)](https://codecov.io/gh/PlatformCommunity/flysystem-bunnycdn) [![Packagist Version](https://img.shields.io/packagist/v/platformcommunity/flysystem-bunnycdn)](https://packagist.org/packages/platformcommunity/flysystem-bunnycdn) ![Minimum PHP Version: 7.2](https://img.shields.io/badge/php-min%207.2-important) [![Licence: MIT](https://img.shields.io/packagist/l/platformcommunity/flysystem-bunnycdn)](https://github.com/PlatformCommunity/flysystem-bunnycdn/blob/master/LICENSE) [![Downloads](https://img.shields.io/packagist/dm/platformcommunity/flysystem-bunnycdn)](https://packagist.org/packages/platformcommunity/flysystem-bunnycdn)

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

## Contributing

Pull requests welcome. Please feel free to lodge any issues as discussion points.
 
## Licence

The Flysystem adapter for BunnyCDN is licensed under [MIT](https://github.com/PlatformCommunity/flysystem-bunnycdn/blob/master/LICENSE). 
