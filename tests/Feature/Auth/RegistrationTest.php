<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    // CHANGED: public self-registration is disabled (staff accounts are created
    // by the SACCO Admin) — these tests now assert the routes are gone.
    public function test_registration_screen_is_disabled()
    {
        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_new_users_cannot_self_register()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertGuest();
        $response->assertStatus(404);
    }
}
