<?php

use Dybasedev\LunaPrototype\Foundation\Configuration\ConfigurationGroup;
use Dybasedev\LunaPrototype\Foundation\Configuration\LunaConfiguration;

if (!function_exists('hash_code')) {
    function hash_code($str): int
    {
        $hash = 0;
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $hash = ($hash * 31 + ord($str[$i])) & 0xFFFFFFFF;
        }
        return $hash;
    }
}

if (!function_exists('short_hash_code')) {
    function short_hash_code($str): int
    {
        $hash = 0;
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $hash = ($hash * 31 + ord($str[$i])) % 255;
        }
        return $hash;
    }
}

if (!function_exists('luna_config')) {
    /**
     * @param string|null $group
     * @return ($group is null ? LunaConfiguration : ConfigurationGroup)
     */
    function luna_config(?string $group = null): LunaConfiguration|ConfigurationGroup
    {
        /** @var LunaConfiguration $configuration */
        $configuration = app('luna.config');

        if ($group) {
            return $configuration->group($group);
        }

        return $configuration;
    }
}