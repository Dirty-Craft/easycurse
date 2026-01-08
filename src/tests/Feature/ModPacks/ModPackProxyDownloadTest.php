<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModPackProxyDownloadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that user can use proxy download endpoint.
     */
    public function test_user_can_use_proxy_download(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        Http::fake([
            'mediafilez.forgecdn.net/*' => Http::response('fake file content', 200, [
                'Content-Type' => 'application/java-archive',
            ]),
        ]);

        $url = urlencode('https://mediafilez.forgecdn.net/files/1234/567/test.jar');
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/proxy-download?url={$url}");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/java-archive');
        $this->assertEquals('fake file content', $response->getContent());
    }

    /**
     * Test that proxy download accepts edge.forgecdn.net domain.
     */
    public function test_proxy_download_accepts_edge_forgecdn_net(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        Http::fake([
            'edge.forgecdn.net/*' => Http::response('fake file content', 200),
        ]);

        $url = urlencode('https://edge.forgecdn.net/files/1234/567/test.jar');
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/proxy-download?url={$url}");

        $response->assertStatus(200);
    }

    /**
     * Test that proxy download handles connection exceptions.
     */
    public function test_proxy_download_handles_connection_exceptions(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        Http::fake([
            'mediafilez.forgecdn.net/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            },
        ]);

        $url = urlencode('https://mediafilez.forgecdn.net/files/1234/567/test.jar');
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/proxy-download?url={$url}");

        $response->assertStatus(504);
        $response->assertJson([
            'error' => 'Connection timeout or network error',
        ]);
    }

    /**
     * Test that proxy download handles generic exceptions.
     */
    public function test_proxy_download_handles_generic_exceptions(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        Http::fake([
            'mediafilez.forgecdn.net/*' => function () {
                throw new \Exception('Unexpected error');
            },
        ]);

        $url = urlencode('https://mediafilez.forgecdn.net/files/1234/567/test.jar');
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/proxy-download?url={$url}");

        $response->assertStatus(500);
        $response->assertJsonStructure(['error']);
    }

    /**
     * Test that proxy download uses default content type when not provided.
     */
    public function test_proxy_download_uses_default_content_type(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        Http::fake([
            'mediafilez.forgecdn.net/*' => Http::response('fake file content', 200), // No Content-Type header
        ]);

        $url = urlencode('https://mediafilez.forgecdn.net/files/1234/567/test.jar');
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/proxy-download?url={$url}");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/java-archive'); // Default
    }

    /**
     * Test that proxy download rejects invalid URLs.
     */
    public function test_proxy_download_rejects_invalid_urls(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $url = urlencode('https://malicious-site.com/file.jar');
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/proxy-download?url={$url}");

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'Invalid download URL',
        ]);
    }

    /**
     * Test that proxy download handles HTTP errors.
     */
    public function test_proxy_download_handles_http_errors(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        Http::fake([
            'mediafilez.forgecdn.net/*' => Http::response('Not Found', 404),
        ]);

        $url = urlencode('https://mediafilez.forgecdn.net/files/1234/567/test.jar');
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/proxy-download?url={$url}");

        $response->assertStatus(404);
        $response->assertJson([
            'error' => 'Failed to download file from CDN',
        ]);
    }

    /**
     * Test that proxy download requires authentication.
     */
    public function test_proxy_download_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $url = urlencode('https://mediafilez.forgecdn.net/files/1234/567/test.jar');
        $response = $this->get("/mod-packs/{$modPack->id}/proxy-download?url={$url}");

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot use proxy download for other user's mod pack.
     */
    public function test_user_cannot_use_proxy_download_for_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $url = urlencode('https://mediafilez.forgecdn.net/files/1234/567/test.jar');
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/proxy-download?url={$url}");

        $response->assertNotFound();
    }

    /**
     * Test that proxy download requires url parameter.
     */
    public function test_proxy_download_requires_url(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/proxy-download");

        $response->assertSessionHasErrors('url');
    }

    /**
     * Test that proxy download for shared modpack works.
     */
    public function test_proxy_download_for_shared_modpack_works(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $token = $modPack->generateShareToken();

        Http::fake([
            'mediafilez.forgecdn.net/files/789/12/test.jar' => Http::response('file content', 200, [
                'Content-Type' => 'application/java-archive',
            ]),
        ]);

        $response = $this->get("/shared/{$token}/proxy-download?url=".urlencode('https://mediafilez.forgecdn.net/files/789/12/test.jar'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/java-archive');
        $this->assertEquals('file content', $response->getContent());
    }

    /**
     * Test that proxy download for shared modpack validates URL domain.
     */
    public function test_proxy_download_for_shared_modpack_validates_url_domain(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $token = $modPack->generateShareToken();

        $response = $this->get("/shared/{$token}/proxy-download?url=".urlencode('https://evil.com/file.jar'));

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'Invalid download URL',
        ]);
    }

    /**
     * Test that sharedProxyDownload handles unsuccessful HTTP response.
     */
    public function test_shared_proxy_download_handles_unsuccessful_http_response(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $token = $modPack->generateShareToken();

        Http::fake([
            'mediafilez.forgecdn.net/files/789/12/test.jar' => Http::response('Not Found', 404),
        ]);

        $response = $this->get("/shared/{$token}/proxy-download?url=".urlencode('https://mediafilez.forgecdn.net/files/789/12/test.jar'));

        $response->assertStatus(404);
        $response->assertJson([
            'error' => 'Failed to download file from CDN',
        ]);
    }

    /**
     * Test that sharedProxyDownload handles ConnectionException.
     */
    public function test_shared_proxy_download_handles_connection_exception(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $token = $modPack->generateShareToken();

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
        });

        $response = $this->get("/shared/{$token}/proxy-download?url=".urlencode('https://mediafilez.forgecdn.net/files/789/12/test.jar'));

        $response->assertStatus(504);
        $response->assertJson([
            'error' => 'Connection timeout or network error',
        ]);
    }

    /**
     * Test that sharedProxyDownload handles general Exception.
     */
    public function test_shared_proxy_download_handles_general_exception(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $token = $modPack->generateShareToken();

        Http::fake(function () {
            throw new \Exception('Unexpected error');
        });

        $response = $this->get("/shared/{$token}/proxy-download?url=".urlencode('https://mediafilez.forgecdn.net/files/789/12/test.jar'));

        $response->assertStatus(500);
        $response->assertJson([
            'error' => 'Failed to proxy download: Unexpected error',
        ]);
    }
}
