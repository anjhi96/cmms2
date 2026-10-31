<?php

use App\Models\User;

it('lets an admin open the page and see the landing link', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/maintenance-intelligence')
        ->assertOk()
        ->assertSee('Maintenance Intelligence Center')
        ->assertSee('AI Sparepart Analyst');

    $this->actingAs($admin)->get('/')
        ->assertOk()
        ->assertSee(route('maintenance-intelligence.index'), false)
        ->assertSee('PM Status');
});

it('hides the link and the page from guests', function () {
    $this->get('/')->assertOk()
        ->assertDontSee('Maintenance Intelligence')
        ->assertSee('PM Status');

    $this->get('/maintenance-intelligence')->assertRedirect(route('login'));
});

it('denies non-admin roles', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get('/')->assertOk()
        ->assertDontSee('Maintenance Intelligence');
    $this->actingAs($user)->get('/maintenance-intelligence')->assertForbidden();
})->with(['pic', 'koordinator', 'supervisor']);

it('renders without the sidebar while other pages keep it', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/maintenance-intelligence')
        ->assertOk()
        ->assertDontSee('lg:ml-72', false)
        ->assertSee(route('home'), false);

    $this->actingAs($admin)->get('/backup')
        ->assertOk()
        ->assertSee('lg:ml-72', false);
});
