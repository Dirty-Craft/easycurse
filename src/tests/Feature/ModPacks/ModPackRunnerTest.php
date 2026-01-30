<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\ModPackRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModPackRunnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a test directory for virtual runs (createRun, stopRun, getRunLogs use this)
        $this->baseDir = '/shared/virtual';
        if (! is_dir($this->baseDir)) {
            File::makeDirectory($this->baseDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Do not delete /shared/virtual here: parallel tests (e.g. PremiumTest) may be using it.
        parent::tearDown();
    }

    /**
     * Test that user can create a run for their mod pack.
     */
    public function test_user_can_create_run(): void
    {
        $user = User::factory()->create([
            'premium_until' => now()->addDays(30),
        ]);

        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'software' => 'fabric',
            'minecraft_version' => '1.20.1',
        ]);

        // Mock ServerJars API for mod loader download
        Http::fake([
            'serverjars.com/api/fetchDetails/*' => Http::response([
                'response' => [
                    'file' => 'https://maven.fabricmc.net/net/fabricmc/fabric-installer/0.15.0/fabric-installer-0.15.0.jar',
                ],
            ], 200),
            'maven.fabricmc.net/*' => Http::response('fake jar content', 200),
        ]);

        // Mock mod download (if mod pack has items)
        Http::fake([
            'mediafilez.forgecdn.net/*' => Http::response('fake mod content', 200),
            'edge.forgecdn.net/*' => Http::response('fake mod content', 200),
            'cdn.modrinth.com/*' => Http::response('fake mod content', 200),
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/runs");

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_pack_runs', [
            'mod_pack_id' => $modPack->id,
            'is_completed' => false,
        ]);
    }

    /**
     * Test that creating a run requires authentication.
     */
    public function test_creating_run_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->post("/mod-packs/{$modPack->id}/runs");

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot create run for other user's mod pack.
     */
    public function test_user_cannot_create_run_for_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/runs");

        $response->assertNotFound();
    }

    /**
     * Test that free user can create runs up to the limit.
     */
    public function test_free_user_can_create_runs_up_to_limit(): void
    {
        $user = User::factory()->create([
            'premium_until' => null,
        ]);

        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'software' => 'fabric',
            'minecraft_version' => '1.20.1',
        ]);

        // Create 9 runs (one less than the limit)
        ModPackRun::factory()->count(9)->create([
            'mod_pack_id' => $modPack->id,
        ]);

        // Mock ServerJars API
        Http::fake([
            'serverjars.com/api/fetchDetails/*' => Http::response([
                'response' => [
                    'file' => 'https://maven.fabricmc.net/net/fabricmc/fabric-installer/0.15.0/fabric-installer-0.15.0.jar',
                ],
            ], 200),
            'maven.fabricmc.net/*' => Http::response('fake jar content', 200),
        ]);

        Http::fake([
            'mediafilez.forgecdn.net/*' => Http::response('fake mod content', 200),
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/runs");

        // Should succeed (this is the 10th run)
        $response->assertRedirect();
        $this->assertDatabaseCount('mod_pack_runs', 10);
    }

    /**
     * Test that free user is redirected to premium page when limit is exceeded.
     */
    public function test_free_user_redirected_to_premium_when_limit_exceeded(): void
    {
        $user = User::factory()->create([
            'premium_until' => null,
        ]);

        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
        ]);

        // Create 10 runs (the limit for free users)
        ModPackRun::factory()->count(10)->create([
            'mod_pack_id' => $modPack->id,
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/runs");

        // Should redirect to premium page
        $response->assertRedirect(route('premium'));
        $response->assertSessionHas('error');
        // Should not create another run
        $this->assertDatabaseCount('mod_pack_runs', 10);
    }

    /**
     * Test that premium user can create unlimited runs.
     */
    public function test_premium_user_can_create_unlimited_runs(): void
    {
        $user = User::factory()->create([
            'premium_until' => now()->addDays(30),
        ]);

        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'software' => 'fabric',
            'minecraft_version' => '1.20.1',
        ]);

        // Create 20 runs (more than the free limit)
        ModPackRun::factory()->count(20)->create([
            'mod_pack_id' => $modPack->id,
        ]);

        // Mock ServerJars API
        Http::fake([
            'serverjars.com/api/fetchDetails/*' => Http::response([
                'response' => [
                    'file' => 'https://maven.fabricmc.net/net/fabricmc/fabric-installer/0.15.0/fabric-installer-0.15.0.jar',
                ],
            ], 200),
            'maven.fabricmc.net/*' => Http::response('fake jar content', 200),
        ]);

        Http::fake([
            'mediafilez.forgecdn.net/*' => Http::response('fake mod content', 200),
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/runs");

        // Should succeed
        $response->assertRedirect();
        $this->assertDatabaseCount('mod_pack_runs', 21);
    }

    /**
     * Test that user can get run history for their mod pack.
     */
    public function test_user_can_get_run_history(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $run1 = ModPackRun::factory()->create([
            'mod_pack_id' => $modPack->id,
            'is_completed' => false,
        ]);

        $run2 = ModPackRun::factory()->create([
            'mod_pack_id' => $modPack->id,
            'is_completed' => true,
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/runs");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'mod_pack_id', 'is_completed', 'created_at', 'updated_at'],
            ],
        ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $runIds = array_column($data, 'id');
        $this->assertContains($run1->id, $runIds);
        $this->assertContains($run2->id, $runIds);
    }

    /**
     * Test that getting run history requires authentication.
     */
    public function test_getting_run_history_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->get("/mod-packs/{$modPack->id}/runs");

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot get run history for other user's mod pack.
     */
    public function test_user_cannot_get_run_history_for_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/runs");

        $response->assertNotFound();
    }

    /**
     * Test that user can get run logs for their mod pack run.
     */
    public function test_user_can_get_run_logs(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $run = ModPackRun::factory()->create([
            'mod_pack_id' => $modPack->id,
        ]);

        $logsPath = $this->baseDir.'/'.$run->id.'/logs.txt';
        $logsDir = dirname($logsPath);
        if (! is_dir($logsDir)) {
            File::makeDirectory($logsDir, 0755, true);
        }
        file_put_contents($logsPath, '[Server thread/INFO]: Server started');

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/runs/{$run->id}/logs");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
        ]);

        $this->assertStringContainsString('Server started', $response->json('data'));

        // Clean up - only remove the file, not the directory (it might contain other files)
        if (file_exists($logsPath)) {
            unlink($logsPath);
        }
    }

    /**
     * Test that getting run logs returns empty string when logs file doesn't exist.
     */
    public function test_getting_run_logs_returns_empty_when_file_missing(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $run = ModPackRun::factory()->create([
            'mod_pack_id' => $modPack->id,
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/runs/{$run->id}/logs");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => '',
        ]);
    }

    /**
     * Test that getting run logs requires authentication.
     */
    public function test_getting_run_logs_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();
        $run = ModPackRun::factory()->create([
            'mod_pack_id' => $modPack->id,
        ]);

        $response = $this->get("/mod-packs/{$modPack->id}/runs/{$run->id}/logs");

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot get run logs for other user's mod pack run.
     */
    public function test_user_cannot_get_run_logs_for_other_user_mod_pack_run(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);
        $run = ModPackRun::factory()->create([
            'mod_pack_id' => $modPack->id,
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/runs/{$run->id}/logs");

        $response->assertNotFound();
    }

    /**
     * Test that user can stop a run for their mod pack.
     * Stop marks the run completed and, when the run directory exists,
     * writes runner.stop so the virtual runner stops the container.
     */
    public function test_user_can_stop_run(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $run = ModPackRun::factory()->create([
            'mod_pack_id' => $modPack->id,
            'is_completed' => false,
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/runs/{$run->id}/stop");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'data' => ['id', 'mod_pack_id', 'is_completed'],
        ]);

        $this->assertDatabaseHas('mod_pack_runs', [
            'id' => $run->id,
            'is_completed' => true,
        ]);
    }

    /**
     * Test that stopping a run writes runner.stop when the run directory exists,
     * so the virtual runner (docker/virtual/runner.sh) stops the container.
     */
    public function test_stop_run_writes_runner_stop_when_run_directory_exists(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $run = ModPackRun::factory()->create([
            'mod_pack_id' => $modPack->id,
            'is_completed' => false,
        ]);

        $runDir = $this->baseDir.'/'.$run->id;
        if (! is_dir($runDir)) {
            File::makeDirectory($runDir, 0755, true);
        }

        $this->actingAs($user)->post("/mod-packs/{$modPack->id}/runs/{$run->id}/stop");

        $this->assertFileExists($runDir.'/runner.stop');
        $this->assertSame('1', file_get_contents($runDir.'/runner.stop'));
    }

    /**
     * Test that stopping a run requires authentication.
     * Unauthenticated requests are redirected to login.
     */
    public function test_stopping_run_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();
        $run = ModPackRun::factory()->create([
            'mod_pack_id' => $modPack->id,
        ]);

        $response = $this->post("/mod-packs/{$modPack->id}/runs/{$run->id}/stop");

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot stop run for another user's mod pack.
     * Returns 404 when the mod pack does not belong to the authenticated user.
     */
    public function test_user_cannot_stop_run_for_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);
        $run = ModPackRun::factory()->create([
            'mod_pack_id' => $modPack->id,
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/runs/{$run->id}/stop");

        $response->assertNotFound();
    }

    /**
     * Test that creating a run with JSON request returns JSON response.
     */
    public function test_creating_run_with_json_request_returns_json_response(): void
    {
        $user = User::factory()->create([
            'premium_until' => now()->addDays(30),
        ]);

        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'software' => 'fabric',
            'minecraft_version' => '1.20.1',
        ]);

        // Mock ServerJars API
        Http::fake([
            'serverjars.com/api/fetchDetails/*' => Http::response([
                'response' => [
                    'file' => 'https://maven.fabricmc.net/net/fabricmc/fabric-installer/0.15.0/fabric-installer-0.15.0.jar',
                ],
            ], 200),
            'maven.fabricmc.net/*' => Http::response('fake jar content', 200),
        ]);

        Http::fake([
            'mediafilez.forgecdn.net/*' => Http::response('fake mod content', 200),
        ]);

        $response = $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->postJson("/mod-packs/{$modPack->id}/runs");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['id', 'mod_pack_id', 'is_completed'],
            'downloaded_count',
            'failed_count',
        ]);
    }

    /**
     * Test that run history is ordered by latest first.
     */
    public function test_run_history_is_ordered_by_latest_first(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $run1 = ModPackRun::factory()->create([
            'mod_pack_id' => $modPack->id,
            'created_at' => now()->subHours(2),
        ]);

        $run2 = ModPackRun::factory()->create([
            'mod_pack_id' => $modPack->id,
            'created_at' => now()->subHour(),
        ]);

        $run3 = ModPackRun::factory()->create([
            'mod_pack_id' => $modPack->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/runs");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(3, $data);
        // Should be ordered by latest first
        $this->assertEquals($run3->id, $data[0]['id']);
        $this->assertEquals($run2->id, $data[1]['id']);
        $this->assertEquals($run1->id, $data[2]['id']);
    }
}
