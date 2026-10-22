<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SessionAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Session-Test-Password-2026!';

    private ?string $sessionDirectory = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionDirectory = sys_get_temp_dir().'/rb-auth-session-'.bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($this->sessionDirectory, 0700));

        config([
            'auth.defaults.guard' => 'web',
            'sanctum.guard' => ['web'],
            'sanctum.stateful' => ['localhost'],
            'session.driver' => 'file',
            'session.files' => $this->sessionDirectory,
            'session.cookie' => 'rb_authentication_test_session',
            'session.domain' => null,
            'session.secure' => false,
            'session.lottery' => [0, 100],
        ]);
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            if ($this->sessionDirectory !== null && is_dir($this->sessionDirectory)) {
                foreach (glob($this->sessionDirectory.'/*') ?: [] as $file) {
                    unlink($file);
                }

                rmdir($this->sessionDirectory);
            }
        }
    }

    #[Test]
    #[DataProvider('apiRequestOrigins')]
    public function api_credentials_persist_in_a_cookie_and_logout_invalidates_the_session(bool $sendOrigin): void
    {
        $user = User::factory()->create(['password' => Hash::make(self::PASSWORD)]);

        $this->browserJson('GET', '/api/user')->assertUnauthorized();

        $login = $this->browserJson('POST', '/api/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ], sendOrigin: $sendOrigin)
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
        $loginCookie = $this->rawSessionCookie($login);

        // A request without the cookie must not retain the previous guard user.
        $this->browserJson('GET', '/api/user')->assertUnauthorized();

        // Sanctum's stateful API requires the frontend Origin on this request,
        // including when the login request itself had no Origin header.
        $this->browserJson('GET', '/api/user', cookie: $loginCookie)
            ->assertOk()
            ->assertJsonPath('id', $user->id);

        $logout = $this->browserJson('POST', '/api/logout', cookie: $loginCookie, sendOrigin: $sendOrigin)
            ->assertOk()
            ->assertJson(['message' => 'Logged out']);

        $this->browserJson('GET', '/api/user', cookie: $this->rawSessionCookie($logout))
            ->assertUnauthorized();
        $this->browserJson('GET', '/api/user', cookie: $loginCookie)
            ->assertUnauthorized();
    }

    public static function apiRequestOrigins(): array
    {
        return [
            'frontend Origin' => [true],
            'no Origin on login and logout' => [false],
        ];
    }

    #[Test]
    public function admin_credentials_allow_the_configured_admin_after_cookie_reload(): void
    {
        $user = $this->credentialUser((int) env('ALLOWED_USER_ID', 10));

        $login = $this->browserForm('POST', '/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/admin/shops');
        $cookie = $this->rawSessionCookie($login);

        $this->browserForm('GET', '/admin/shops')->assertRedirect('/login');
        $this->browserForm('GET', '/admin/shops', cookie: $cookie)->assertOk();
    }

    #[Test]
    public function admin_credentials_forbid_another_user_after_cookie_reload(): void
    {
        $user = $this->credentialUser((int) env('ALLOWED_USER_ID', 10) + 1);

        $login = $this->browserForm('POST', '/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/admin/shops');

        $this->browserForm('GET', '/admin/shops', cookie: $this->rawSessionCookie($login))
            ->assertForbidden();
    }

    private function credentialUser(int $id): User
    {
        return User::factory()->create([
            'id' => $id,
            'password' => Hash::make(self::PASSWORD),
        ]);
    }

    private function browserJson(string $method, string $uri, array $data = [], ?string $cookie = null, bool $sendOrigin = true): TestResponse
    {
        $this->prepareBrowserRequest($cookie);

        return $this->json($method, 'http://localhost'.$uri, $data, $sendOrigin ? [
            'Origin' => 'http://localhost',
        ] : []);
    }

    private function browserForm(string $method, string $uri, array $data = [], ?string $cookie = null): TestResponse
    {
        $this->prepareBrowserRequest($cookie);

        return $this->call($method, 'http://localhost'.$uri, $data, $this->prepareCookiesForRequest(), [], [
            'HTTP_ORIGIN' => 'http://localhost',
            'HTTP_ACCEPT' => 'text/html',
        ]);
    }

    private function prepareBrowserRequest(?string $cookie): void
    {
        // PHPUnit uses Laravel's testing-mode CSRF exemption. These requests
        // exercise real credential validation and session-cookie persistence.
        // Reset shared state so every request must load its user from the cookie.
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->app->forgetInstance('auth.driver');
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');

        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        $this->withCredentials();

        if ($cookie !== null) {
            // Preserve the response's encrypted value without encrypting it twice.
            $this->withUnencryptedCookie((string) config('session.cookie'), $cookie);
        }
    }

    private function rawSessionCookie(TestResponse $response): string
    {
        $cookie = $response->getCookie((string) config('session.cookie'), false);
        $this->assertNotNull($cookie, 'The response must issue a session cookie.');
        $value = $cookie->getValue();
        $this->assertIsString($value);
        $this->assertNotSame('', $value);

        return $value;
    }
}
