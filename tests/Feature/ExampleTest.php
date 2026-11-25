<?php

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

it('forwards old storefront links without accepting new orders at the old host', function () {
    config(['app.storefront_forward_url' => 'https://b-crunch.example']);

    $this->get('/caja?panel=cortes')
        ->assertRedirect('https://b-crunch.example/caja?panel=cortes');

    $this->postJson(route('api.orders.store'), [])
        ->assertConflict();
});
