<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModPackReminderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that user can set a reminder for Minecraft version update.
     */
    public function test_user_can_set_reminder_for_minecraft_version_update(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/set-reminder", [
            'minecraft_version' => '1.21.1',
            'software' => 'fabric',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Reminder set successfully',
        ]);

        // Verify reminder fields are set
        $modPack->refresh();
        $this->assertEquals('1.21.1', $modPack->minecraft_update_reminder_version);
        $this->assertEquals('fabric', $modPack->minecraft_update_reminder_software);
    }

    /**
     * Test that setting reminder requires authentication.
     */
    public function test_setting_reminder_requires_authentication(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->post("/mod-packs/{$modPack->id}/set-reminder", [
            'minecraft_version' => '1.21.1',
            'software' => 'fabric',
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot set reminder for other user's mod pack.
     */
    public function test_user_cannot_set_reminder_for_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/set-reminder", [
            'minecraft_version' => '1.21.1',
            'software' => 'fabric',
        ]);

        $response->assertNotFound();
    }

    /**
     * Test that setting reminder requires minecraft_version.
     */
    public function test_setting_reminder_requires_minecraft_version(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/mod-packs/{$modPack->id}/set-reminder", [
                'software' => 'fabric',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['minecraft_version']);
    }

    /**
     * Test that setting reminder requires software.
     */
    public function test_setting_reminder_requires_software(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/mod-packs/{$modPack->id}/set-reminder", [
                'minecraft_version' => '1.21.1',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['software']);
    }

    /**
     * Test that setting reminder validates software enum.
     */
    public function test_setting_reminder_validates_software_enum(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/mod-packs/{$modPack->id}/set-reminder", [
                'minecraft_version' => '1.21.1',
                'software' => 'invalid',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['software']);
    }

    /**
     * Test that setting reminder accepts all valid software types.
     */
    public function test_setting_reminder_accepts_all_valid_software_types(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $validSoftwareTypes = ['forge', 'fabric', 'quilt', 'neoforge'];

        foreach ($validSoftwareTypes as $software) {
            $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/set-reminder", [
                'minecraft_version' => '1.21.1',
                'software' => $software,
            ]);

            $response->assertStatus(200);

            // Verify reminder was set
            $modPack->refresh();
            $this->assertEquals('1.21.1', $modPack->minecraft_update_reminder_version);
            $this->assertEquals($software, $modPack->minecraft_update_reminder_software);

            // Clear for next iteration
            $modPack->update([
                'minecraft_update_reminder_version' => null,
                'minecraft_update_reminder_software' => null,
            ]);
        }
    }

    /**
     * Test that reminder can be updated.
     */
    public function test_reminder_can_be_updated(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_update_reminder_version' => '1.20.1',
            'minecraft_update_reminder_software' => 'forge',
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/set-reminder", [
            'minecraft_version' => '1.21.1',
            'software' => 'fabric',
        ]);

        $response->assertStatus(200);

        // Verify reminder was updated
        $modPack->refresh();
        $this->assertEquals('1.21.1', $modPack->minecraft_update_reminder_version);
        $this->assertEquals('fabric', $modPack->minecraft_update_reminder_software);
    }
}
