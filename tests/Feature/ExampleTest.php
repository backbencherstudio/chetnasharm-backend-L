<?php

test('the application returns a successful response', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('assets/img/logo/logo.webp', false)
        ->assertSee('Under construction', false);
});
