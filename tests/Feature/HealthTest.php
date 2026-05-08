<?php

declare(strict_types=1);

it('GET /up returns 200', function (): void {
    $response = $this->get('/up');

    $response->assertStatus(200);
});
