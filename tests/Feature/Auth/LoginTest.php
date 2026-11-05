<?php

namespace Tests\Feature\Auth;

use App\Models\HITUAM01\HITUAM_MSHUSER;
use App\Services\HITUAM\AuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    private function makeUser(string $password = 'secret'): HITUAM_MSHUSER
    {
        $user = new HITUAM_MSHUSER;
        $user->IID = 1;
        $user->VUSERNAME = 'john.doe';
        $user->VNAME = 'John Doe';
        $user->VPASSWORD = Hash::make($password);

        return $user;
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = $this->makeUser();

        $service = \Mockery::mock(AuthService::class);
        $service->shouldReceive('getUserLdap')
            ->once()
            ->with('john.doe', 'secret')
            ->andReturn($user);
        $service->shouldReceive('userLoggedInOtherDevice')
            ->once()
            ->with($user)
            ->andReturnFalse();

        $this->instance(AuthService::class, $service);

        $response = $this
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->postJson('/HITUAM/web-api/auth/login', [
                'username' => 'john.doe',
                'password' => 'secret',
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'redirect',
            ]);
    }

    public function test_login_fails_when_user_logged_in_elsewhere(): void
    {
        $user = $this->makeUser();

        $service = \Mockery::mock(AuthService::class);
        $service->shouldReceive('getUserLdap')
            ->once()
            ->with('john.doe', 'secret')
            ->andReturn($user);
        $service->shouldReceive('userLoggedInOtherDevice')
            ->once()
            ->with($user)
            ->andReturnTrue();

        $this->instance(AuthService::class, $service);

        $response = $this
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->postJson('/HITUAM/web-api/auth/login', [
                'username' => 'john.doe',
                'password' => 'secret',
            ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'You logged in another device.',
                'errors' => [
                    'username' => ['You logged in another device.'],
                ],
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = $this->makeUser('correct-password');

        $service = \Mockery::mock(AuthService::class);
        $service->shouldReceive('getUserLdap')
            ->once()
            ->with('john.doe', 'wrong-password')
            ->andReturn($user);
        $service->shouldReceive('userLoggedInOtherDevice')->never();

        $this->instance(AuthService::class, $service);

        $response = $this
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->postJson('/HITUAM/web-api/auth/login', [
                'username' => 'john.doe',
                'password' => 'wrong-password',
            ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'You have entered an invalid username or password',
                'errors' => [
                    'username' => ['You have entered an invalid username or password'],
                ],
            ]);
    }

    public function test_logout_redirects_to_login_and_invalidates_session(): void
    {
        $this->withoutMiddleware(\Illuminate\Auth\Middleware\Authenticate::class);

        Auth::shouldReceive('id')->once()->andReturn(null);
        Auth::shouldReceive('logout')->once();

        $response = $this
            ->withSession(['foo' => 'bar'])
            ->post('/HITUAM/auth/logout');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionMissing('foo');
    }
}
