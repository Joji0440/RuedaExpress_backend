<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function unauthenticated_users_cannot_access_admin_routes()
    {
        $response = $this->get('/admin/users');
        $response->assertRedirect('/login');

        $response = $this->get('/manage-users');
        $response->assertRedirect('/login');
    }

    /** @test */
    public function users_without_roles_cannot_access_admin_routes()
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get('/admin/users');
        $response->assertStatus(403);

        $response = $this->get('/manage-users');
        $response->assertStatus(403);
    }

    /** @test */
    public function admins_can_access_admin_routes()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin);

        $response = $this->get('/admin/users');
        $response->assertStatus(200);
    }

    /** @test */
    public function moderators_can_access_manage_users_routes()
    {
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');

        $this->actingAs($moderator);

        $response = $this->get('/manage-users');
        $response->assertStatus(200);
    }
}