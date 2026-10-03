<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La racine renvoie vers la connexion : la présentation est sur la vitrine.
     */
    public function test_la_racine_renvoie_vers_la_connexion(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
