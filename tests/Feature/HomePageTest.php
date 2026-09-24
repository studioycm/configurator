<?php

test('the homepage leads to the authenticated dashboard while the public catalog is deferred', function () {
    $this->get(route('home'))->assertRedirect(route('dashboard'));
    $this->followingRedirects()->get(route('home'))->assertOk()->assertSee('Log in');
});
