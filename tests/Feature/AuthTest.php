<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\Concerns\WithoutMiddleware;


// CSRF token verification is bypassed in tests (no browser to supply the token)
beforeEach(function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
});

it('redirects guests to login for protected pages', function () {
    $this->get('/orders/tables')->assertRedirect('/login');
    $this->get('/menu')->assertRedirect('/login');
    $this->get('/reports/sales')->assertRedirect('/login');
});

it('allows a valid user to log in', function () {
    $user = User::factory()->create(['email' => 'test@wjcafe.com', 'password' => bcrypt('secret123')]);

    $response = $this->post('/login', [
        'email' => 'test@wjcafe.com',
        'password' => 'secret123',
    ]);

    $response->assertRedirect(route('orders.tables'));
    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    User::factory()->create(['email' => 'test@wjcafe.com', 'password' => bcrypt('secret123')]);

    $this->post('/login', ['email' => 'test@wjcafe.com', 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('lets staff access orders but blocks admin pages', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    $this->actingAs($staff)->get('/orders/tables')->assertStatus(200);
    $this->actingAs($staff)->get('/menu')->assertStatus(403);
    $this->actingAs($staff)->get('/reports/sales')->assertStatus(403);
    $this->actingAs($staff)->get('/inventory')->assertStatus(403);
    $this->actingAs($staff)->get('/users')->assertStatus(403);
});

it('lets admin access all pages', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/orders/tables')->assertStatus(200);
    $this->actingAs($admin)->get('/menu')->assertStatus(200);
    $this->actingAs($admin)->get('/reports/sales')->assertStatus(200);
    $this->actingAs($admin)->get('/inventory')->assertStatus(200);
    $this->actingAs($admin)->get('/users')->assertStatus(200);
});

it('allows an admin to create a staff user', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/users', [
        'name' => 'New Staff',
        'email' => 'newstaff@wjcafe.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'staff',
    ])->assertRedirect();

    $this->assertDatabaseHas('users', ['email' => 'newstaff@wjcafe.com', 'role' => 'staff']);
});

it('logs out a user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});
