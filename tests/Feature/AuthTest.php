<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_can_be_rendered_for_guests(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->has('torActive')
            ->has('demoCredentials')
            ->where('demoCredentials.email', 'operator@darkdump.local')
        );
    }

    public function test_authenticated_operators_are_redirected_to_dashboard_from_login(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('login'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_operator_can_authenticate_using_valid_email_and_password(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@darkdump.local',
            'password' => Hash::make('DarkDump@2026!'),
        ]);

        $response = $this->post('/login', [
            'login' => 'operator@darkdump.local',
            'password' => 'DarkDump@2026!',
            'remember' => true,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_operator_can_authenticate_using_username_handle(): void
    {
        $user = User::factory()->create([
            'name' => 'Operator Prime',
            'email' => 'operator@darkdump.local',
            'password' => Hash::make('DarkDump@2026!'),
        ]);

        $response = $this->post('/login', [
            'login' => 'Operator Prime',
            'password' => 'DarkDump@2026!',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_operator_cannot_authenticate_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'operator@darkdump.local',
            'password' => Hash::make('DarkDump@2026!'),
        ]);

        $response = $this->from(route('login'))->post('/login', [
            'login' => 'operator@darkdump.local',
            'password' => 'WrongPassword!',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('login');
    }

    public function test_authentication_is_rate_limited_after_excessive_failed_attempts(): void
    {
        User::factory()->create([
            'email' => 'operator@darkdump.local',
            'password' => Hash::make('DarkDump@2026!'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'login' => 'operator@darkdump.local',
                'password' => 'WrongPassword!',
            ]);
        }

        // 6th attempt should be blocked by rate limiter
        $response = $this->post('/login', [
            'login' => 'operator@darkdump.local',
            'password' => 'WrongPassword!',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertStringContainsString('Too many authentication failures', session('errors')->first('login'));
    }

    public function test_operator_can_logout_and_session_is_destroyed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }
}
