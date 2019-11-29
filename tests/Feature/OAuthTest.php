<?php

namespace Tests\Feature;

use Tests\TestCase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var Mockery\MockInterface|Laravel\Socialite\Two\User
     */
    private $user;

    /**
     * @var Mockery\MockInterface|Laravel\Socialite\Contracts\Provider
     */
    private $provider;

    /**
     * @var string
     */
    private $providerName;

    public function setUp(): void
    {
        parent::setUp();

        Mockery::getConfiguration()->allowMockingNonExistentMethods(false);

        $this->providerName = 'google';

        // モックを作成
        $this->user = Mockery::mock('Laravel\Socialite\Two\User');
        $this->user
            ->shouldReceive('getId')
            ->andReturn(uniqid())
            ->shouldReceive('getEmail')
            ->andReturn(uniqid() . '@test.com')
            ->shouldReceive('getNickname')
            ->andReturn('Pseudo');

        $this->provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $this->provider->shouldReceive('user')->andReturn($this->user);
    }

    public static function tearDownAfterClass(): void
    {
        // Mockeryの設定をもとに戻す
        Mockery::getConfiguration()->allowMockingNonExistentMethods(true);
    }

    /**
     * @test
     */
    public function Googleの認証画面を表示できる()
    {
        // URLをコール
        $response = $this->get(route('socialOAuth', ['provider' => $this->providerName]));
        $response->assertStatus(302);

        $target = parse_url($response->headers->get('location'));
        // リダイレクト先ドメインの検証
        $this->assertEquals('accounts.google.com', $target['host']);

        // パラメータの検証
        $query = explode('&', $target['query']);
        $this->assertContains('redirect_uri=' . urlencode(config('services.google.redirect')), $query);
        $this->assertContains('client_id=' . config('services.google.client_id'), $query);
    }

    /**
     * @test
     */
    public function Googleアカウントでユーザー登録できる()
    {
        Socialite::shouldReceive('driver')->with($this->providerName)->andReturn($this->provider);

        // URLをコール
        $this->get(route('oauthCallback', ['service' => $this->providerName]))
            ->assertStatus(302)
            ->assertRedirect(route('home'));

        // 各データが正しく登録されているかチェック
        $this->assertDatabaseHas('users', [
            'provider_id' => $this->user->getId(),
            'provider_name' => $this->providerName,
            'name' => $this->user->getNickName(),
            'email' => $this->user->getEmail()
        ]);

        // 認証チェック
        $this->assertAuthenticated();
    }
}
