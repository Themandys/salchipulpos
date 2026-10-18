<?php

use App\Models\Product;

it('blocks the admin api without the PIN', function () {
    $this->getJson(route('api.admin.dashboard'))->assertUnauthorized();
    $this->patchJson(route('api.admin.products.update', Product::factory()->create()), ['price' => 1])->assertUnauthorized();
});

it('rejects a wrong PIN', function () {
    $this->postJson(route('api.admin.session.store'), ['pin' => '0000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('pin');
});

it('unlocks the admin api with the correct PIN', function () {
    $this->postJson(route('api.admin.session.store'), ['pin' => '1234'])->assertOk();

    $this->getJson(route('api.admin.dashboard'))
        ->assertOk()
        ->assertJsonStructure(['today', 'current_hour', 'hourly_cuts', 'daily_cuts', 'sales', 'products']);
});

it('lets the admin change the PIN', function () {
    $this->postJson(route('api.admin.session.store'), ['pin' => '1234'])->assertOk();
    $this->patchJson(route('api.admin.settings.update'), ['business_pin' => '9876'])->assertOk();
    $this->deleteJson(route('api.admin.session.destroy'))->assertOk();

    $this->postJson(route('api.admin.session.store'), ['pin' => '1234'])->assertUnprocessable();
    $this->postJson(route('api.admin.session.store'), ['pin' => '9876'])->assertOk();
});
