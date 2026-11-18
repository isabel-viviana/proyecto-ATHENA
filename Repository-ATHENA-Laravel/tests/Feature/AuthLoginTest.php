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

    public function test_firebase_login_issues_sanctum_token_for_protected_endpoints_and_logout(): void
    {
        // Generar par de claves RSA efímero y certificado autofirmado para simular Google SecureToken
        $sslConfig = ['config' => dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf'];
        $privateKey = openssl_pkey_new($sslConfig + [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $csr = openssl_csr_new(['commonName' => 'securetoken.system.gserviceaccount.com'], $privateKey, $sslConfig);
        $x509 = openssl_csr_sign($csr, null, $privateKey, 1, $sslConfig);
        openssl_x509_export($x509, $certPem);

        $kid = 'test-firebase-kid-2026';
        $projectId = 'athena-595d2';
        config(['services.firebase.project_id' => $projectId]);
        \Illuminate\Support\Facades\Cache::put('firebase_google_certs', [$kid => $certPem], 3600);

        // Crear usuario ID 1 distinto para comprobar que nunca hay fallback al ID 1
        $user1 = User::factory()->create([
            'full_name' => 'Usuario Uno',
            'email' => 'user1@athena.edu.co',
        ]);

        $now = time();
        $b64Url = fn (string $d) => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
        $headerB64 = $b64Url(json_encode(['alg' => 'RS256', 'kid' => $kid, 'typ' => 'JWT']));
        $payloadB64 = $b64Url(json_encode([
            'iss' => "https://securetoken.google.com/{$projectId}",
            'aud' => $projectId,
            'sub' => 'firebase-uid-student-2',
            'email' => 'firebase.student2@athena.edu.co',
            'name' => 'Estudiante Firebase Dos',
            'iat' => $now - 10,
            'exp' => $now + 3600,
        ]));

        openssl_sign("{$headerB64}.{$payloadB64}", $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $firebaseJwt = "{$headerB64}.{$payloadB64}." . $b64Url($signature);

        $loginRes = $this->postJson('/api/auth/firebase-login', [
            'firebase_token' => $firebaseJwt,
        ]);

        $loginRes->assertOk()
            ->assertJsonStructure(['user' => ['id', 'email', 'role'], 'token', 'access_token']);

        $sanctumToken = $loginRes->json('token');
        $firebaseUserId = $loginRes->json('user.id');
        $this->assertNotEquals($user1->id, $firebaseUserId);

        // Verificar GET /api/auth/me con el token Sanctum emitido por firebase-login
        $this->withToken($sanctumToken)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $firebaseUserId)
            ->assertJsonPath('user.email', 'firebase.student2@athena.edu.co');

        // Verificar que GET /api/progress identifica al usuario de Firebase y devuelve 0 simulacros (no los de user1)
        $this->withToken($sanctumToken)
            ->getJson('/api/progress/stats')
            ->assertOk()
            ->assertJsonPath('data.total_simulacros_completados', 0);

        // Verificar logout revoca el token Sanctum
        $this->withToken($sanctumToken)
            ->postJson('/api/auth/logout')
            ->assertOk();

        Auth::forgetGuards();

        $this->withToken($sanctumToken)
            ->getJson('/api/progress')
            ->assertUnauthorized();
    }

    public function test_private_endpoints_require_sanctum_while_public_endpoints_remain_accessible(): void
    {
        Auth::forgetGuards();

        // Endpoints públicos legítimos deben seguir accesibles
        $this->getJson('/api/subscriptions/plans')->assertOk();

        // Endpoints privados de perfil, notificaciones, suscripciones personales y administración deben exigir Sanctum (401)
        $this->getJson('/api/profile')->assertUnauthorized();
        $this->putJson('/api/profile', [])->assertUnauthorized();
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->patchJson('/api/notifications/1/read')->assertUnauthorized();
        $this->getJson('/api/subscriptions/my-subscription')->assertUnauthorized();
        $this->postJson('/api/subscriptions/wompi-checkout', [])->assertUnauthorized();
        $this->getJson('/api/users')->assertUnauthorized();
        $this->getJson('/api/subjects')->assertUnauthorized();
        $this->getJson('/api/topics')->assertUnauthorized();
        $this->getJson('/api/questions')->assertUnauthorized();
    }

    public function test_admin_routes_enforce_role_authorization_across_all_roles(): void
    {
        Auth::forgetGuards();

        $adminEndpoints = [
            ['GET', '/api/users'],
            ['POST', '/api/users'],
            ['GET', '/api/users/1'],
            ['PUT', '/api/users/1'],
            ['DELETE', '/api/users/1'],
            ['PATCH', '/api/users/1/role'],
            ['PATCH', '/api/users/1/status'],
            ['GET', '/api/subjects'],
            ['POST', '/api/subjects'],
            ['GET', '/api/subjects/1'],
            ['PUT', '/api/subjects/1'],
            ['DELETE', '/api/subjects/1'],
            ['GET', '/api/topics'],
            ['POST', '/api/topics'],
            ['GET', '/api/topics/1'],
            ['PUT', '/api/topics/1'],
            ['DELETE', '/api/topics/1'],
            ['GET', '/api/questions'],
            ['POST', '/api/questions'],
            ['GET', '/api/questions/1'],
            ['PUT', '/api/questions/1'],
            ['DELETE', '/api/questions/1'],
        ];

        // 1. Sin token: 401
        foreach ($adminEndpoints as [$method, $uri]) {
            $this->json($method, $uri)->assertStatus(401);
        }

        // 2. student autenticado: 403
        $student = User::factory()->create(['email' => 'student.role@athena.edu.co', 'role' => 'student']);
        \Laravel\Sanctum\Sanctum::actingAs($student);
        foreach ($adminEndpoints as [$method, $uri]) {
            $this->json($method, $uri)->assertStatus(403);
        }

        // 3. support autenticado: 403
        $support = User::factory()->create(['email' => 'support.role@athena.edu.co', 'role' => 'support']);
        \Laravel\Sanctum\Sanctum::actingAs($support);
        foreach ($adminEndpoints as [$method, $uri]) {
            $this->json($method, $uri)->assertStatus(403);
        }

        // 4. admin autenticado: 200 permitido
        $admin = User::factory()->create(['email' => 'admin.role@athena.edu.co', 'role' => 'admin']);
        \Laravel\Sanctum\Sanctum::actingAs($admin);
        foreach ($adminEndpoints as [$method, $uri]) {
            $this->json($method, $uri)->assertStatus(200);
        }

        // 5. superadmin autenticado: 200 permitido
        $superadmin = User::factory()->create(['email' => 'superadmin.role@athena.edu.co', 'role' => 'superadmin']);
        \Laravel\Sanctum\Sanctum::actingAs($superadmin);
        foreach ($adminEndpoints as [$method, $uri]) {
            $this->json($method, $uri)->assertStatus(200);
        }

        // 6. super_admin autenticado: 200 permitido
        $superAdminUnderscore = User::factory()->create(['email' => 'super_admin.role@athena.edu.co', 'role' => 'super_admin']);
        \Laravel\Sanctum\Sanctum::actingAs($superAdminUnderscore);
        foreach ($adminEndpoints as [$method, $uri]) {
            $this->json($method, $uri)->assertStatus(200);
        }
    }

    public function test_diagnostic_test_db_routes_require_superadmin_role(): void
    {
        Auth::forgetGuards();

        $diagnosticRoutes = [
            '/api/test-db/integrity',
            '/api/test-db/academic',
            '/api/test-db/simulators',
            '/api/test-db/commercial-ai',
            '/api/test-db/hybrid',
            '/api/test-db/gemini',
        ];

        // 1. Sin token: 401
        foreach ($diagnosticRoutes as $uri) {
            $this->getJson($uri)->assertStatus(401);
        }

        // 2. student: 403
        $student = User::factory()->create(['email' => 'diag.student@athena.edu.co', 'role' => 'student']);
        \Laravel\Sanctum\Sanctum::actingAs($student);
        foreach ($diagnosticRoutes as $uri) {
            $this->getJson($uri)->assertStatus(403);
        }

        // 3. support: 403
        $support = User::factory()->create(['email' => 'diag.support@athena.edu.co', 'role' => 'support']);
        \Laravel\Sanctum\Sanctum::actingAs($support);
        foreach ($diagnosticRoutes as $uri) {
            $this->getJson($uri)->assertStatus(403);
        }

        // 4. admin: 403
        $admin = User::factory()->create(['email' => 'diag.admin@athena.edu.co', 'role' => 'admin']);
        \Laravel\Sanctum\Sanctum::actingAs($admin);
        foreach ($diagnosticRoutes as $uri) {
            $this->getJson($uri)->assertStatus(403);
        }

        // 5. superadmin y super_admin: acceso permitido (no 401 ni 403)
        User::factory()->create([
            'full_name' => 'Juan Sebastian Acevedo Medina',
            'email' => 'juan.acevedo@athena.edu.co',
            'role' => 'student',
        ]);

        $mockGemini = \Mockery::mock(\App\Services\GeminiService::class);
        $mockGemini->shouldReceive('generateText')->andReturn('ATHENA Gemini funcionando.');
        $this->app->instance(\App\Services\GeminiService::class, $mockGemini);

        $superadmin = User::factory()->create(['email' => 'diag.superadmin@athena.edu.co', 'role' => 'superadmin']);
        \Laravel\Sanctum\Sanctum::actingAs($superadmin);
        foreach ($diagnosticRoutes as $uri) {
            $this->getJson($uri)->assertStatus(200);
        }

        $superAdminAlt = User::factory()->create(['email' => 'diag.super_admin@athena.edu.co', 'role' => 'super_admin']);
        \Laravel\Sanctum\Sanctum::actingAs($superAdminAlt);
        foreach ($diagnosticRoutes as $uri) {
            $this->getJson($uri)->assertStatus(200);
        }
    }

    public function test_subscriptions_authenticated_flow_and_public_webhook(): void
    {
        Auth::forgetGuards();

        // Webhook y planes continúan siendo públicos
        $this->getJson('/api/subscriptions/plans')->assertOk()->assertJsonPath('success', true);
        $this->postJson('/api/subscriptions/webhook', [
            'data' => [
                'transaction' => [
                    'id' => 'wmp_tx_public_webhook_01',
                    'status' => 'APPROVED',
                ],
            ],
        ])->assertOk()->assertJson(['status' => 'received']);

        // my-subscription y wompi-checkout requieren autenticación
        $this->getJson('/api/subscriptions/my-subscription')->assertUnauthorized();
        $this->postJson('/api/subscriptions/wompi-checkout', [
            'plan_id' => 'premium_monthly',
            'transaction_id' => 'wmp_tx_unauth',
        ])->assertUnauthorized();

        // Usuario autenticado obtiene su suscripción real y checkout exige autenticación (pasa auth:sanctum y llega al controlador)
        $student = User::factory()->create(['email' => 'sub.student@athena.edu.co', 'role' => 'student']);
        $otherStudent = User::factory()->create(['email' => 'other.sub@athena.edu.co', 'role' => 'student']);

        $plan = \App\Models\SubscriptionPlan::create([
            'name' => 'Premium Trimestral',
            'price' => 79900,
            'duration_days' => 90,
            'description' => 'Plan trimestral de prueba',
            'is_active' => true,
        ]);

        // Crear suscripción para otro usuario para verificar aislamiento
        \App\Models\Subscription::create([
            'user_id' => $otherStudent->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(90)->toDateString(),
            'status' => 'active',
            'auto_renew' => true,
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($student);

        // Antes de tener suscripción: subscription es null (sin el antiguo fallback simulado) y no devuelve la de otherStudent
        $this->getJson('/api/subscriptions/my-subscription')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'subscription' => null,
                'transactions' => [],
            ]);

        // Con usuario autenticado, wompi-checkout supera auth:sanctum (devuelve 422 al validar campos requeridos en vez de 401)
        $this->postJson('/api/subscriptions/wompi-checkout', [])
            ->assertStatus(422);

        // Asociar suscripción y transacción real al estudiante autenticado
        $sub = \App\Models\Subscription::create([
            'user_id' => $student->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(90)->toDateString(),
            'status' => 'active',
            'auto_renew' => true,
        ]);

        \App\Models\PaymentTransaction::create([
            'user_id' => $student->id,
            'subscription_id' => $sub->id,
            'provider' => 'mercado_pago',
            'provider_transaction_id' => 'tx_student_100',
            'amount' => 79900,
            'currency' => 'COP',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        // Ahora my-subscription devuelve la suscripción y transacción del usuario autenticado con el contrato JSON esperado
        $mySubResponse = $this->getJson('/api/subscriptions/my-subscription');
        $mySubResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('subscription.id', $sub->id)
            ->assertJsonPath('subscription.user_id', $student->id)
            ->assertJsonPath('subscription.status', 'active')
            ->assertJsonCount(1, 'transactions');
    }
}
