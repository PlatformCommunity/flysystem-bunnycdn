<?php

namespace PlatformCommunity\Flysystem\BunnyCDN;

use DateTimeInterface;

class BunnyPullZone
{
    protected string $pullzoneUrl;
    protected string $pullzoneSecurityToken;

    /**
     * Create a new BunnyPullZone, which represents a pull zone on BunnyCDN.
     *
     * @param string $pullzoneUrl The URL of the pull zone, including http:// or https://
     * @param string $pullzoneSecurityToken If this pull zone is protected, then pass the security token here
     */
    public function __construct(string $pullzoneUrl, string $pullzoneSecurityToken = '')
    {
        $this->pullzoneUrl = $pullzoneUrl;
        $this->pullzoneSecurityToken = $pullzoneSecurityToken;
    }

    /**
     * Returns the public URL for the target path behind the pull zone
     *
     * @param string $path
     * @return string
     */
    public function publicUrl(string $path): string
    {
        return rtrim($this->pullzoneUrl, '/').'/'.ltrim($path, '/');
    }

    /**
     * Returns a signed, temporary URL for an asset. Image optimization parameters are supported.
     *
     * @param string $path the path of the file to fetch
     * @param DateTimeInterface $expiresAt expiration date of the signed url
     * @param array $urlParameters optional parameters including: remote_ip, countries, & settings for bunny optimizer
     * @param string $allowForPath pass an optional path to enable access to related objects that may be in the same folder. Unused, requires some fine tuning.
     *
     * @return string
     */
    public function temporaryUrl(string $path, DateTimeInterface $expiresAt, array $urlParameters = [], string $allowForPath = ''): string
    {
        // bunny requires params in ascending order
        ksort($urlParameters);
        $queryParameters = http_build_query($urlParameters);
        $expiration = $expiresAt->getTimestamp();
        $basePathToHash = $this->pullzoneSecurityToken . $path . $expiresAt->getTimestamp() . $queryParameters;
        $hash = hash('sha256', $basePathToHash, true);
        // per docs, assemble and strip extra characters out
        $token = base64_encode($hash);
        $token = strtr($token, '+/', '-_');
        $token = str_replace('=', '', $token);
        // original params can be added after the token, but before expires
        if(!empty($queryParameters)) {
            $queryParameters = '&' . $queryParameters;
        }
        // assemble the final path and merge it back with the pullzone_url
        $signedPath =  "?token={$token}{$queryParameters}&expires={$expiration}";

        return rtrim($this->pullzoneUrl, '/') . $path . $signedPath;
    }
}