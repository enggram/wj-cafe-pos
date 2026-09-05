<?php

it('redirects unauthenticated users to login', function () {
    $this->withoutVite();

    $response = $this->get('/');

    // Root redirects to /orders/tables which requires auth → login
    $response->assertRedirect();
});

it('shows the login page', function () {
    $this->withoutVite();

    $response = $this->get('/login');

    $response->assertStatus(200);
});
