<img src="https://dka575ofm4ao0.cloudfront.net/pages-transactional_logos/retina/20630/bunnycdn-logo-dark.png" width="300"/>

# Flysystem Adapter for BunnyCDN.

## Installation

_TBA_ 

```bash
composer require platformcommunity/flysystem-bunnycdn
```

## Usage

```php
use OpenCloud\OpenStack;
use OpenCloud\Rackspace;
use PlatformCommunity\Flysystem\Filesystem;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter as Adapter;

$client = new BunnyCDNAdapter(Rackspace::UK_IDENTITY_ENDPOINT, array(
    'username' => ':username',
    'apiKey' => ':password',
));

$store = $client->objectStoreService('cloudFiles', 'LON');
$container = $store->getContainer('flysystem');

$filesystem = new Filesystem(new Adapter($container));
```
