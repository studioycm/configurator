<?php

test('guests can open the configurator home page and find the admin login', function () {
    config(['app.name' => 'Aquestia']);

    $this->get(route('home'))
        ->assertViewIs('home')
        ->assertSeeText('Aquestia')
        ->assertSeeText('Log in to admin panel')
        ->assertSee('href="'.route('filament.admin.auth.login').'"', false);
});
