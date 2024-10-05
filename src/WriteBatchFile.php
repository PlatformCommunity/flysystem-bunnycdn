<?php

namespace PlatformCommunity\Flysystem\BunnyCDN;

class WriteBatchFile
{
    public function __construct(
        public string $localPath,
        public string $targetPath,
    ) {
    }
}
