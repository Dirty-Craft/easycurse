<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModPackRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that mod pack user relationship works.
     */
    public function test_mod_pack_user_relationship(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $modPack->user);
        $this->assertEquals($user->id, $modPack->user->id);
    }

    /**
     * Test that user mod packs relationship works.
     */
    public function test_user_mod_packs_relationship(): void
    {
        $user = User::factory()->create();
        ModPack::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->modPacks);
        $this->assertInstanceOf(ModPack::class, $user->modPacks->first());
    }
}
