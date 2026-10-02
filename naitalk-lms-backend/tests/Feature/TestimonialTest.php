<?php

use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->owner = makeUserWithRole('owner');
    $this->student = makeUserWithRole('student');

    $this->ownerToken = $this->owner->createToken('test')->plainTextToken;
    $this->studentToken = $this->student->createToken('test')->plainTextToken;
});

it('lets an owner create a testimonial, and it appears in the public testimonials list', function () {
    $response = $this->postJson('/api/v1/admin/testimonials', [
        'quote' => 'This platform changed how our team learns.',
        'author' => 'Ada, L&D Lead',
    ], [
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->assertCreated();

    expect($response->json('data.quote'))->toBe('This platform changed how our team learns.');
    expect($response->json('data.author'))->toBe('Ada, L&D Lead');

    // Public — no auth required.
    $this->getJson('/api/v1/testimonials')->assertOk()->assertJsonCount(1, 'data');
});

it('lets an owner delete a testimonial, and it disappears from the public list immediately', function () {
    $created = $this->postJson('/api/v1/admin/testimonials', [
        'quote' => 'This platform changed how our team learns.',
        'author' => 'Ada, L&D Lead',
    ], [
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->assertCreated()->json('data');

    $this->deleteJson("/api/v1/admin/testimonials/{$created['id']}", [], [
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->assertOk();

    $this->getJson('/api/v1/testimonials')->assertOk()->assertJsonCount(0, 'data');
});

it('rejects testimonial creation from a user without settings.manage', function () {
    $this->postJson('/api/v1/admin/testimonials', [
        'quote' => 'This platform changed how our team learns.',
        'author' => 'Ada, L&D Lead',
    ], [
        'Authorization' => "Bearer {$this->studentToken}",
    ])->assertStatus(403);
});

it('requires both quote and author', function () {
    $this->postJson('/api/v1/admin/testimonials', [], [
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->assertStatus(422);
});
