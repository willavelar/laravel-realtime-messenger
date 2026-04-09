<?php

namespace Tests\Feature\Auth;

use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postGraphQL([
            'query' => '
                mutation {
                    register(input: {
                        name: "John Doe"
                        email: "john@example.com"
                        password: "password123"
                        password_confirmation: "password123"
                    }) {
                        token
                        user { id name email }
                    }
                }
            ',
        ]);

        $response->assertJsonPath('data.register.user.email', 'john@example.com');
        $this->assertNotNull($response->json('data.register.token'));
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postGraphQL([
            'query' => '
                mutation {
                    login(input: {
                        email: "john@example.com"
                        password: "password123"
                    }) {
                        token
                        user { id email }
                    }
                }
            ',
        ]);

        $response->assertJsonPath('data.login.user.email', 'john@example.com');
        $this->assertNotNull($response->json('data.login.token'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postGraphQL([
            'query' => '
                mutation {
                    login(input: {
                        email: "john@example.com"
                        password: "wrongpassword"
                    }) {
                        token
                    }
                }
            ',
        ]);

        $this->assertNotNull($response->json('errors'));
    }
}
