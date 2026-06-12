<?php

namespace Tests\Unit\Services;

use App\Exceptions\ResponseException;
use App\Models\HITUAM01\HITUAM_MSHUSER;
use App\Services\HITUAM\AuthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    private function makeUser(): HITUAM_MSHUSER
    {
        $user = new HITUAM_MSHUSER;
        $user->IID = 10;

        return $user;
    }

    public function test_get_user_ldap_returns_null_when_url_missing(): void
    {
        config([
            'services.ldap.url' => null,
            'services.ldap.token' => 'token',
        ]);

        $service = new AuthService;

        $this->assertNull($service->getUserLdap('john.doe', 'secret'));
    }

    public function test_get_user_ldap_throws_when_token_missing(): void
    {
        config([
            'services.ldap.url' => 'https://ldap.test',
            'services.ldap.token' => null,
        ]);

        $service = new AuthService;

        $this->expectException(ResponseException::class);
        $this->expectExceptionMessage('API Token has not been set in the Configuration menu');

        $service->getUserLdap('john.doe', 'secret');
    }

    public function test_get_user_ldap_throws_when_ldap_response_unsuccessful(): void
    {
        config([
            'services.ldap.url' => 'https://ldap.test',
            'services.ldap.token' => 'ldap-token',
        ]);

        Http::fake([
            'https://ldap.test' => Http::response([], 500),
        ]);

        $service = new AuthService;

        $this->expectException(ResponseException::class);
        $this->expectExceptionMessage('Error occured in LDAP Server');

        $service->getUserLdap('john.doe', 'secret');
    }

    public function test_get_user_ldap_returns_existing_user_when_found(): void
    {
        config([
            'services.ldap.url' => 'https://ldap.test',
            'services.ldap.token' => 'ldap-token',
        ]);

        Http::fake([
            'https://ldap.test' => Http::response([
                'success' => false,
                'username' => 'ldap-username',
                'npk' => '123456',
                'email' => 'ldap@example.test',
            ], 200),
        ]);

        $existingUser = $this->makeUser();
        $existingUser->VUSERNAME = 'john.doe';

        $query = \Mockery::mock();
        $query->shouldReceive('where')->once()->with('VUSERNAME', 'john.doe')->andReturnSelf();
        $query->shouldReceive('where')->once()->with('VNIK', 'ldap-username')->andReturnSelf();
        $query->shouldReceive('first')->once()->andReturn($existingUser);

        $service = \Mockery::mock(AuthService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('newUserQuery')->once()->andReturn($query);

        $this->assertSame($existingUser, $service->getUserLdap('john.doe', 'secret'));
    }

    public function test_get_user_ldap_creates_user_when_not_found(): void
    {
        config([
            'services.ldap.url' => 'https://ldap.test',
            'services.ldap.token' => 'ldap-token',
        ]);

        Http::fake([
            'https://ldap.test' => Http::response([
                'success' => false,
                'username' => 'ldap-username',
                'npk' => '7777',
                'email' => 'ldap@example.test',
            ], 200),
        ]);

        $query = \Mockery::mock();
        $query->shouldReceive('where')->once()->with('VUSERNAME', 'john.doe')->andReturnSelf();
        $query->shouldReceive('where')->once()->with('VNIK', 'ldap-username')->andReturnSelf();
        $query->shouldReceive('first')->once()->andReturn(null);

        $createdUser = $this->makeUser();
        $createdUser->VUSERNAME = 'ldap-username';
        $createdUser->VEMAIL = 'ldap@example.test';

        $service = \Mockery::mock(AuthService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('newUserQuery')->once()->andReturn($query);
        $service->shouldReceive('createUserFromLdap')
            ->once()
            ->with(\Mockery::on(function ($data) {
                $this->assertSame('7777', $data['VNIK']);
                $this->assertSame('ldap-username', $data['VNAME']);
                $this->assertSame('ldap-username', $data['VUSERNAME']);
                $this->assertSame('ldap@example.test', $data['VEMAIL']);

                return Hash::check('secret', $data['VPASSWORD']);
            }))
            ->andReturn($createdUser);

        $this->assertSame($createdUser, $service->getUserLdap('john.doe', 'secret'));
    }

    public function test_user_logged_in_other_device_returns_true_when_session_exists(): void
    {
        $user = $this->makeUser();

        $query = \Mockery::mock();
        $connection = \Mockery::mock();

        DB::shouldReceive('connection')
            ->once()
            ->with('hituam')
            ->andReturn($connection);

        $connection->shouldReceive('table')
            ->once()
            ->with('sessions')
            ->andReturn($query);

        $query->shouldReceive('where')
            ->once()
            ->with('user_id', $user->IID)
            ->andReturnSelf();

        $query->shouldReceive('where')
            ->once()
            ->with('application', config('app.code'))
            ->andReturnSelf();

        $query->shouldReceive('exists')
            ->once()
            ->andReturnTrue();

        $service = new AuthService;

        $this->assertTrue($service->userLoggedInOtherDevice($user));
    }

    public function test_user_logged_in_other_device_returns_false_when_session_missing(): void
    {
        $user = $this->makeUser();

        $query = \Mockery::mock();
        $connection = \Mockery::mock();

        DB::shouldReceive('connection')->once()->with('hituam')->andReturn($connection);

        $connection->shouldReceive('table')
            ->once()
            ->with('sessions')
            ->andReturn($query);

        $query->shouldReceive('where')
            ->once()
            ->with('user_id', $user->IID)
            ->andReturnSelf();

        $query->shouldReceive('where')
            ->once()
            ->with('application', config('app.code'))
            ->andReturnSelf();

        $query->shouldReceive('exists')
            ->once()
            ->andReturnFalse();

        $service = new AuthService;

        $this->assertFalse($service->userLoggedInOtherDevice($user));
    }

    public function test_user_logged_in_other_device_throws_response_exception_on_failure(): void
    {
        $user = $this->makeUser();

        DB::shouldReceive('connection')
            ->once()
            ->with('hituam')
            ->andThrow(new \Exception('connection error'));

        $service = new AuthService;

        $this->expectException(ResponseException::class);

        $service->userLoggedInOtherDevice($user);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
