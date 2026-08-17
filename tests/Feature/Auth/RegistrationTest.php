<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/dashboard'); // Will hit the middleware, then onboarding

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Test User', $user->name);
        $this->assertTrue(Hash::check('password', $user->password));

        // Ensure no tenant or membership was created globally
        $this->assertEquals(0, \App\Modules\Tenancy\Models\Tenant::count());
        $this->assertEquals(0, \App\Modules\Tenancy\Models\Membership::count());
    }

    public function test_registration_requires_valid_data(): void
    {
        // Email duplicado
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->post('/register', [
            'name' => 'Another User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();

        // Confirmação de senha divergente
        $response2 = $this->post('/register', [
            'name' => 'Valid User',
            'email' => 'valid@example.com',
            'password' => 'password',
            'password_confirmation' => 'wrong',
        ]);

        $response2->assertSessionHasErrors(['password']);
        $this->assertGuest();
    }
}
