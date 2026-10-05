<?php

test('the application returns a successful response', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('assets/img/logo/logo.webp', false)
        ->assertSee('Under construction', false);
});
