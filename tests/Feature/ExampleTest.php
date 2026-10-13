<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_ai_cam_page_returns_a_successful_response(): void
    {
        $response = $this->get('/ai-cam');

        $response->assertStatus(200);
        $response->assertSee('Asisten Penglihatan');
    }

    public function test_detect_requires_an_image(): void
    {
        $response = $this->postJson('/api/ai-cam/detect', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('image');
    }
}
