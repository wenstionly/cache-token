<?php


namespace Wenstionly\CacheToken;


use Illuminate\Support\Facades\Cache;

/**
 * Class CacheTokenManager
 *
 * Cache Structure:
 *   Token to User id Map:
 *       {prefix(T2U)}.{agent_prefix}.token => uid
 *   User id to Token Map:
 *       {prefix(U2T).uid => [
 *           {agent_prefix}.token => uid,
 *           ...
 *       ]
 * @package Wenstionly\CacheToken
 */
class CacheTokenManager
{
    protected $t2uPrefix;
    protected $u2tPrefix;
    protected $store;

    protected $agentPrefix;
    protected $agentExpire;

    public function __construct($agent)
    {
        $this->t2uPrefix = config('cache_token.token_map_prefix');
        $this->u2tPrefix = config('cache_token.user_map_prefix');
        $this->store = config('cache_token.store');
        if(is_array($agent)) {
            $this->agentPrefix = $agent['prefix'] ?? '';
            $this->agentExpire = $agent['expire'] ?? 600;
        }
        else {
            $this->agentPrefix = null;
            $this->agentExpire = 600;
        }
    }

    public function check($token)
    {
        $token = $this->agentPrefix.$token;
        $k = $this->t2uPrefix.$token;

        $store = Cache::store($this->store);
        $u = $store->get($k);
        if(!$u)
            return null;
        $store->put($k, $u, $this->agentExpire);
        return $u;
    }

    public function remove($token)
    {
        $token = $this->agentPrefix.$token;
        $k = $this->t2uPrefix.$token;

        $store = Cache::store($this->store);
        $u =$store->get($k);
        $store->forget($k);
        if($u) {
            $uk = "{$this->u2tPrefix}{$u}";
            $list =$store->get($uk);
            if($list) {
                unset($list[$token]);
                $store->forever($uk, $list);
            }
        }
    }

    public function conflict($uid, $forceAll = false)
    {
        if(is_null($this->agentPrefix))
            $forceAll = true;
        $k = $this->u2tPrefix.$uid;
        $store = Cache::store($this->store);
        $list = $store->get($k);
        if($list) {
            $prefix = $this->agentPrefix;
            $prefixLen = strlen($prefix);
            foreach($list as $token => $id) {
                if($forceAll || !$prefix || (strncmp($token, $prefix, $prefixLen) == 0)) {
                    unset($list[$token]);
                    $store->forget($this->t2uPrefix.$token);
                }
            }
            $store->forever($k, $list);
        }
    }

    public function create($uid)
    {
        $cleanToken = static::generate();
        $token = $this->agentPrefix.$cleanToken;
        $k = $this->t2uPrefix.$token;
        $store = Cache::store($this->store);

        $store->put($k, $uid, $this->agentExpire);
        $this->flushUserMap($uid);

        $uk = $this->u2tPrefix.$uid;
        $list = $store->get($uk, []);
        $list[$token] = $uid;
        $store->forever($uk, $list);
        return $cleanToken;
    }

    public function flushUserMap($uid)
    {
        $store = Cache::store($this->store);

        $k = $this->u2tPrefix.$uid;
        $list = $store->get($k);
        if($list) {
            foreach($list as $token => $id) {
                if($store->has($this->t2uPrefix.$token))
                    continue;
                unset($list[$token]);
            }
            $store->forever($k, $list);
        }
    }

    private static function generate()
    {
        if(function_exists('com_create_guid')) {
            $guid = com_create_guid();
            return str_replace('-', '', substr($guid, 1, strlen($guid) - 2));
        }
        else {
            mt_srand(doubleval(microtime(true) * 10000));
            return strtoupper(md5(uniqid(mt_rand(), true)));
        }
    }
}
