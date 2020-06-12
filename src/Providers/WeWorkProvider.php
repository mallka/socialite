<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\Exceptions\MethodDoesNotSupportException;
use Overtrue\Socialite\User;

class WeWorkProvider extends Base
{
    public const NAME = 'wework-provider';
    protected ?int $agentId;
    protected bool $detailed = false;
    protected ?string $apiAccessToken;

    /**
     * @param int $agentId
     *
     * @return $this
     */
    public function setAgentId(int $agentId)
    {
        $this->agentId = $agentId;

        return $this;
    }

    public function detailed(): self
    {
        $this->detailed = true;

        return $this;
    }

    public function userFromCode(string $code): User
    {
        $token = $this->getApiAccessToken();
        $user = $this->getUserId($token, $code);

        if ($this->detailed && isset($user['user_ticket'])) {
            return $this->getDetailedUser($token, $user['user_ticket']);
        }

        $this->detailed = false;

        return $this->mapUserToObject($user)->setProvider($this)->setRaw($user);
    }

    protected function getAuthUrl(): string
    {
        // 网页授权登录
        if (!empty($this->scopes)) {
            return $this->getOAuthUrl();
        }

        // 第三方网页应用登录（扫码登录）
        return $this->getQrConnectUrl();
    }

    protected function getOAuthUrl(): string
    {
        $queries = [
            'appid' => $this->getClientId(),
            'redirect_uri' => $this->redirectUrl,
            'response_type' => 'code',
            'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
            'agentid' => $this->agentId,
            'state' => $this->state,
        ];

        return sprintf('https://open.weixin.qq.com/connect/oauth2/authorize?%s#wechat_redirect', http_build_query($queries));
    }

    protected function getQrConnectUrl()
    {
        $queries = [
            'appid' => $this->getClientId(),
            'agentid' => $this->agentId,
            'redirect_uri' => $this->redirectUrl,
            'state' => $this->state,
        ];

        return 'https://open.work.weixin.qq.com/wwopen/sso/qrConnect?'.http_build_query($queries);
    }

    protected function getTokenUrl(): string
    {
        return '';
    }

    /**
     * @param string $token
     *
     * @return array
     * @throws \Overtrue\Socialite\Exceptions\MethodDoesNotSupportException
     */
    protected function getUserByToken(string $token): array
    {
        throw new MethodDoesNotSupportException('WeWork doesn\'t support access_token mode');
    }

    protected function getApiAccessToken()
    {
        return $this->apiAccessToken ?? $this->apiAccessToken = $this->createApiAccessToken();
    }

    /**
     * @param string $apiAccessToken
     *
     * @return $this
     */
    public function withApiAccessToken(string $apiAccessToken)
    {
        $this->apiAccessToken = $apiAccessToken;

        return $this;
    }

    /**
     * @param string $token
     * @param string $code
     *
     * @return array
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     */
    protected function getUserId(string $token, string $code): array
    {
        $response = $this->getHttpClient()->get('https://qyapi.weixin.qq.com/cgi-bin/user/getuserinfo', [
            'query' => array_filter([
                'access_token' => $token,
                'code' => $code,
            ]),
        ]);

        $response = \json_decode($response->getBody(), true) ?? [];

        if (($response['errcode'] ?? 1) > 0 || empty($response['UserId'])) {
            throw new AuthorizeFailedException('Failed to get user openid:'. $response['errmsg'] ?? 'Unknown.', $response);
        }

        return $response;
    }

    /**
     * @param string $token
     * @param string $ticket
     *
     * @return mixed
     */
    protected function getDetailedUser(string $token, string $ticket): array
    {
        $response = $this->getHttpClient()->post('https://qyapi.weixin.qq.com/cgi-bin/user/getuserdetail', [
            'query' => [
                'access_token' => $token,
            ],
            'json' => [
                'user_ticket' => $ticket,
            ],
        ]);

        return \json_decode($response->getBody(), true) ?? [];
    }

    /**
     * @param array $user
     *
     * @return \Overtrue\Socialite\User
     */
    protected function mapUserToObject(array $user): User
    {
        if ($this->detailed) {
            return new User([
                'id' => $user['userid'] ?? null,
                'name' => $user['name'] ?? null,
                'avatar' => $user['avatar'] ?? null,
                'email' => $user['email'] ?? null,
            ]);
        }

        return new User(array_filter([
            'id' => $user['UserId'] ?? null ?: $user['OpenId'] ?? null,
            'userId' => $user['UserId'] ?? null,
            'openid' => $user['OpenId'] ?? null,
            'deviceId' => $user['DeviceId'] ?? null,
        ]));
    }

    /**
     * @return mixed
     * @throws \Overtrue\Socialite\Exceptions\AuthorizeFailedException
     */
    protected function createApiAccessToken(): mixed
    {
        $response = $this->getHttpClient()->get('https://qyapi.weixin.qq.com/cgi-bin/gettoken', [
            'query' => array_filter([
                'corpid' => $this->config->get('corp_id') ?? $this->config->get('corpid'),
                'corpsecret' => $this->config->get('corp_secret') ?? $this->config->get('corpsecret'),
            ]),
        ]);

        $response = \json_decode($response->getBody(), true) ?? [];

        if (($response['errcode'] ?? 1) > 0) {
            throw new AuthorizeFailedException('Failed to get api access_token:' . $response['errmsg'] ?? 'Unknown.', $response);
        }

        return $response['access_token'];
    }
}
