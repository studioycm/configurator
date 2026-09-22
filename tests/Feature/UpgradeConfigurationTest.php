<?php

use Illuminate\Support\Facades\Cache;

test('persistent cache preserves array data without restoring arbitrary PHP objects', function () {
    $cache = Cache::store('database');
    $cache->put('upgrade-array', ['part' => 'valve', 'quantity' => 2], 60);
    $cache->put('upgrade-object', (object) ['part' => 'valve'], 60);

    expect($cache->get('upgrade-array'))->toBe(['part' => 'valve', 'quantity' => 2]);
    expect($cache->get('upgrade-object'))->toBeInstanceOf(__PHP_Incomplete_Class::class);
});

test('sessions persist JSON while preserving flash and authentication values', function () {
    $session = app('session')->driver('array');
    $session->start();
    $session->put('login_web_test', 12);
    $session->flash('status', 'Saved');

    $session->save();

    $payload = json_decode($session->getHandler()->read($session->getId()), true, 512, JSON_THROW_ON_ERROR);
    expect($payload['login_web_test'])->toBe(12);
    expect($payload['status'])->toBe('Saved');
    expect($payload['_flash']['old'])->toContain('status');
});
