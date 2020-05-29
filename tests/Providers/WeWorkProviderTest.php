<?php

use Overtrue\Socialite\Providers\WeWorkProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class WeWorkProviderTest extends TestCase
{
    public function testQrConnect()
    {
        $response = (new WeWorkProvider(Request::create('foo'), [
            'client_id' => 'ww100000a5f2191',
            'client_secret' => 'client_secret',
            'redirect' => 'http://www.oa.com',
        ]))
            ->setAgentId('1000000')
            ->stateless()
            ->redirect();

        $this->assertSame('https://open.work.weixin.qq.com/wwopen/sso/qrConnect?appid=ww100000a5f2191&agentid=1000000&redirect_uri=http%3A%2F%2Fwww.oa.com', $response->getTargetUrl());
    }

    public function testOAuthWithAgentId()
    {
        $response = (new WeWorkProvider(Request::create('foo'), [
            'client_id' => 'CORPID',
            'client_secret' => 'client_secret',
            'redirect' => 'REDIRECT_URI',
        ]))
            ->scopes(['snsapi_base'])
            ->setAgentId('1000000')
            ->stateless()
            ->redirect();

        $this->assertSame('https://open.weixin.qq.com/connect/oauth2/authorize?appid=CORPID&redirect_uri=REDIRECT_URI&response_type=code&scope=snsapi_base&agentid=1000000#wechat_redirect', $response->getTargetUrl());
    }

    public function testOAuthWithoutAgentId()
    {
        $response = (new WeWorkProvider(Request::create('foo'), [
            'client_id' => 'CORPID',
            'client_secret' => 'client_secret',
            'redirect' => 'REDIRECT_URI',
        ]))
            ->scopes(['snsapi_base'])
            ->stateless()
            ->redirect();

        $this->assertSame('https://open.weixin.qq.com/connect/oauth2/authorize?appid=CORPID&redirect_uri=REDIRECT_URI&response_type=code&scope=snsapi_base#wechat_redirect', $response->getTargetUrl());
    }
}
