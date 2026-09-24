<?php

test('homepage is reachable', function () {
    $this->get(route('home'))
        ->assertRedirect('/dashboard');
});
