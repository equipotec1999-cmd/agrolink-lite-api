<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

/** Versión Lite: el feed es más liviano pero sigue cumpliendo el contrato que usa la app. */
class LiteFeedTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // En CI el .env.example trae el disco s3 sin bucket y ListingMediaResource intentaría
        // resolver la URL contra S3. Con el disco público la URL se arma localmente.
        config(['filesystems.default' => 'public']);
    }

    private function fotos(int $publicacionId, int $cuantas): void
    {
        for ($i = 0; $i < $cuantas; $i++) {
            DB::table('medios_publicacion')->insert([
                'publicacion_id' => $publicacionId, 'tipo' => 'foto',
                'ruta_almacenamiento' => "listings/{$publicacionId}/{$i}.jpg", 'posicion' => $i + 1,
                'creado_en' => now(), 'actualizado_en' => now(),
            ]);
        }
    }

    public function test_el_feed_trae_solo_la_foto_de_portada_y_la_ficha_trae_todas(): void
    {
        $lista = $this->makeListing($this->makeUser());
        $this->fotos($lista->id, 3);

        $this->getJson('/api/listings')
            ->assertOk()
            ->assertJsonCount(1, 'data.0.media');

        $this->getJson("/api/listings/{$lista->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data.media');
    }

    public function test_la_portada_del_feed_es_la_primera_foto_por_posicion(): void
    {
        $lista = $this->makeListing($this->makeUser());
        $this->fotos($lista->id, 3);

        $portadaFeed = $this->getJson('/api/listings')->json('data.0.media.0');
        $primeraFicha = $this->getJson("/api/listings/{$lista->id}")->json('data.media.0');

        $this->assertSame($primeraFicha, $portadaFeed);
    }

    public function test_el_feed_pagina_sin_total_pero_con_enlace_a_la_siguiente_pagina(): void
    {
        $dueno = $this->makeUser();
        for ($i = 0; $i < 21; $i++) {
            $this->makeListing($dueno);
        }

        $pagina1 = $this->getJson('/api/listings')->assertOk();
        $pagina1->assertJsonCount(20, 'data')->assertJsonMissingPath('meta.total');
        $this->assertNotNull($pagina1->json('links.next'));

        $this->getJson('/api/listings?page=2')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_el_catalogo_se_puede_cachear_en_el_cliente(): void
    {
        $cabecera = $this->getJson('/api/categories')->assertOk()->headers->get('Cache-Control');

        // Symfony reordena las directivas, por eso no se compara el texto exacto.
        $this->assertStringContainsString('max-age=300', $cabecera);
        $this->assertStringContainsString('public', $cabecera);
    }

    public function test_la_raiz_responde_json_ligero(): void
    {
        $this->getJson('/')->assertOk()->assertJsonPath('edition', 'lite');
    }
}
