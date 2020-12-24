<?php


namespace Wenstionly\CacheToken;


use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Jenssegers\Agent\Facades\Agent;

class CacheTokenDriver implements Guard
{
    use GuardHelpers;

    const NOAGENT = '';
    const DESKTOP = 'desktop';
    const MOBILE = 'mobile';
    const TABLET = 'tablet';

    const DEFAULT_AGENTS = [
        self::NOAGENT => [
            'name' => self::NOAGENT,
            'expire' => 600,
            'conflict' => false,
        ],
        self::DESKTOP => [
            'name' => self::DESKTOP,
            'expire' => 600,
            'conflict' => false,
        ],
        self::MOBILE => [
            'name' => self::MOBILE,
            'expire' => 7*24*60*60,
            'conflict' => true,
        ],
        self::TABLET => [
            'name' => self::TABLET,
            'expire' => 7*24*60*60,
            'conflict' => true,
        ],
    ];

    /**
     * The request instance
     *
     * @var Request
     */
    protected $request;

    /**
     * The name of the query string item from the request containing the API token
     * @var string
     */
    protected $inputKey;

    /**
     * The name of the header key from the request containing the auth sign
     *
     * @var string
     */
    protected $headerKey;

    protected $agentEnabled;
    protected $agentCfg;
    protected $currentAgentCfg;

    protected $tokenManager;
    /**
     * Create a new authentication guard.
     *
     * @param UserProvider $provider
     * @param Request $request
     * @param string $inputKey
     */
    public function __construct($provider, Request $request, $config = [])
    {
        $this->request = $request;
        $this->provider = $provider;
        $this->inputKey = $config['input_key'] ?? 'api_token';
        $this->headerKey = $config['header_key'] ?? 'header_key';

        $this->agentEnabled = true;
        if(isset($config['agents'])) {
            $agentCfg = $config['agents'];
        }
        else if(isset($config['agent'])) {
            $this->agentEnabled = false;
            $agentCfg = [
                self::NOAGENT => $config['agent']
            ];
        }
        else {
            $agentCfg = [];
        }
        $this->agentCfg = [];
        foreach (self::DEFAULT_AGENTS as $key => $cfg) {
            $this->agentCfg[$key] = array_merge($cfg, $agentCfg[$key] ?? []);
        }
        $headerToken = $this->headerKey ? $this->request->header($this->headerKey) : '';
//        Log::debug("检查是否设置了header认证 {$headerToken}");
        if(!is_null($headerToken)) {
            // 优先检查是否设置了header认证
            $agentType = self::MOBILE;
        }
        else if($this->agentEnabled) {
            if (Agent::isMobile())
                $agentType = self::MOBILE;
            else if (Agent::isTablet())
                $agentType = self::TABLET;
            else
                $agentType = self::DESKTOP;
        }
        else
            $agentType = self::NOAGENT;
        $this->currentAgentCfg = $this->agentCfg[$agentType];
        $this->currentAgentCfg['prefix'] = $agentType ? "$agentType." : '';

        $this->tokenManager = new CacheTokenManager($this->currentAgentCfg);
    }

    /**
     * Get the currently authenticated user.
     *
     * @return Authenticatable|null
     */
    public function user()
    {
        if (! is_null($this->user)) {
            return $this->user;
        }

        $this->user = $this->checkUserOfToken($this->getTokenForRequest());
//        if($this->user) {
//            $this->user->api = Menu::getApiMap($this->user->acl);
//        }
        return $this->user;
    }

    /**
     * Validate a user's credentials.
     *
     * @param  array  $credentials
     * @return bool
     */
    public function validate(array $credentials = [])
    {
        // 检查是否存在请求中的数据
        $token = $credentials[$this->inputKey] ?? '';

        return !!$this->checkUserOfToken($token);
    }

    /**
     * Get the token for the current request.
     *
     * @return string
     */
    public function getTokenForRequest()
    {
        if($this->headerKey) {
            $token = $this->request->header($this->headerKey);
            if(!is_null($token))
                $token = @explode(',', $token)[1];
        }

        if(empty($token)) {
            $token = $this->request->query($this->inputKey);
        }

        if(empty($token)) {
            $token = $this->request->input($this->inputKey);
        }

        return $token;
    }

    /**
     * Check token valid or not
     * @param $token
     * @return mixed
     */
    public function checkUserOfToken($token) {
        if(!$token)
            return null;

        $uid = $this->tokenManager->check($token);
        if(!$uid)
            return null;
        return $this->provider->retrieveById($uid);
    }

    /**
     * Set the current request instance.
     * @param Request $request
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function create($uid) {
        if($this->currentAgentCfg['conflict'])
            $this->tokenManager->conflict($uid);
        return $this->tokenManager->create($uid);
    }

    public function logout() {
        $token = $this->getTokenForRequest();
        if($token)
            $this->tokenManager->remove($token);
    }

    public function conflict($forceAll = false, $uid = null) {
        if($uid === null)
            $uid = Auth::guard()->id();
        if(!$uid)
            return;
        $this->tokenManager->conflict($uid, $forceAll);
    }

    public function id() {
        return $this->user ? $this->user->id : 0;
    }

    public function agentType() {
        return $this->user ? $this->currentAgentCfg['name'] : false;
    }

    public function isDesktop() {
        return $this->currentAgentCfg['name'] === self::DESKTOP;
    }

    public function isMobile() {
        return $this->currentAgentCfg['name'] === self::MOBILE;
    }

    public function isTablet() {
        return $this->currentAgentCfg['name'] === self::TABLET;
    }

}
