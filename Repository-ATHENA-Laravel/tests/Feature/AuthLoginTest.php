<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_register_with_a_persisted_account(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'full_name' => 'Estudiante ATHENA',
            'email' => 'student@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'user' => ['id', 'email'],
                'token',
            ])
            ->assertJsonPath('user.email', 'student@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'student@example.com',
            'full_name' => 'Estudiante ATHENA',
        ]);
    }

    public function test_user_can_login_and_use_the_returned_token(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'correct-password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'student@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'email'],
                'token',
            ]);

        $token = $response->json('token');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'correct-password',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'student@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'El correo o la contraseña son incorrectos.',
            ]);
    }
}
