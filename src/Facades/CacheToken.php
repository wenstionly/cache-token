<?php


namespace Wenstionly\CacheToken\Facades;


use Illuminate\Support\Facades\Facade;

/**
 * Class CacheToken
 * @package Wenstionly\CacheToken\Facades
 *
 * @method static void conflict(int $uid)
 *
 */
class CacheToken extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'CacheToken';
    }
}
