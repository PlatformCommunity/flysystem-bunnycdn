# Flysystem Adapter for BunnyCDN.


## Installation

_TBA_ 

```bash
composer require sifex/flysystem-bunnycdn
```

## Usage

```php
use OpenCloud\OpenStack;
use OpenCloud\Rackspace;
use Sifex\Flysystem\Filesystem;
use Sifex\Flysystem\BunnyCDN\BunnyCDNAdapter as Adapter;

$client = new Rackspace(Rackspace::UK_IDENTITY_ENDPOINT, array(
    'username' => ':username',
    'apiKey' => ':password',
));

$store = $client->objectStoreService('cloudFiles', 'LON');
$container = $store->getContainer('flysystem');

$filesystem = new Filesystem(new Adapter($container));
```
