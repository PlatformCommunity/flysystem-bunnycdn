<?php

namespace PlatformCommunity\Flysystem\BunnyCDN;

/**
 * Utility Class
 */
class Util
{
    /**
     * Splits a path into a file and a directory
     *
     * @param $path
     * @return array
     */
    public static function splitPathIntoDirectoryAndFile($path): array
    {
        $path = self::endsWith($path, '/') ? substr($path, 0, -1) : $path;
        $sub = explode('/', $path);
        $file = array_pop($sub);
        $directory = implode('/', $sub);

        return [
            'file' => $file,
            'dir' => $directory,
        ];
    }

    /**
     * @param $path
     * @param  bool  $isDirectory
     * @return false|string|string[]
     */
    public static function normalizePath($path, $isDirectory = false)
    {
        $path = str_replace('\\', '/', $path);

        if ($isDirectory && ! self::endsWith($path, '/')) {
            $path .= '/';
        }

        // Remove double slashes
        while (strpos($path, '//') !== false) {
            $path = str_replace('//', '/', $path);
        }

        // Remove the starting slash
        if (strpos($path, '/') === 0) {
            $path = substr($path, 1);
        }

        return $path;
    }

    /**
     * @codeCoverageIgnore
     *
     * @param $haystack
     * @param $needle
     * @return bool
     */
    public static function startsWith($haystack, $needle): bool
    {
        return strpos($haystack, $needle) === 0;
    }

    /**
     * @param $haystack
     * @param $needle
     * @return bool
     */
    public static function endsWith($haystack, $needle): bool
    {
        $length = strlen($needle);
        if ($length === 0) {
            return true;
        }

        return substr($haystack, -$length) === $needle;
    }

    public static function replaceFirst(string $search, string $replace, string $subject): string
    {
        $position = strpos($subject, $search);

        if ($position !== false) {
            return (string) substr_replace($subject, $replace, $position, strlen($search));
        }

        return $subject;
    }
}
