<?php

test('public registration screen is unavailable', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});

test('new users cannot register publicly', function () {
    $response = $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
});
