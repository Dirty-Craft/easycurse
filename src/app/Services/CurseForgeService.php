<?php

namespace App\Services;

use App\Enums\Software;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurseForgeService
{
    private string $baseUrl;

    private string $apiKey;

    private string $minecraftGameId;

    private string $minecraftModsClassId;

    public function __construct()
    {
        $this->baseUrl = config('services.curseforge.base_url');
        $this->apiKey = config('services.curseforge.api_key');
        $this->minecraftGameId = config('services.curseforge.minecraft_game_id');
        $this->minecraftModsClassId = config('services.curseforge.minecraft_mods_class_id');
    }

    /**
     * Get the HTTP client with authentication headers.
     */
    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Accept' => 'application/json',
        ]);
    }

    /**
     * Search for mods by slug.
     */
    public function searchModBySlug(string $slug): ?array
    {
        try {
            $response = $this->client()->get($this->baseUrl.'mods/search', [
                'gameId' => $this->minecraftGameId,
                'classId' => $this->minecraftModsClassId,
                'slug' => $slug,
            ]);

            $response->throw();

            $data = $response->json('data');

            return ! empty($data) ? $data[0] : null;
        } catch (RequestException $e) {
            Log::error('CurseForge API error searching mod by slug', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get mod details by ID.
     */
    public function getMod(int $modId): ?array
    {
        try {
            $response = $this->client()->get($this->baseUrl.'mods/'.$modId);

            $response->throw();

            return $response->json('data');
        } catch (RequestException $e) {
            Log::error('CurseForge API error getting mod', [
                'mod_id' => $modId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get files for a mod.
     */
    public function getModFiles(int $modId, ?string $gameVersion = null, ?Software $software = null): array
    {
        try {
            $queryParams = [];

            if ($gameVersion) {
                $queryParams['gameVersion'] = $gameVersion;
            }

            if ($software) {
                // Map Software enum to CurseForge mod loader type
                // 1 = Forge, 4 = Fabric, 2 = Quilt (uses same as Fabric)
                $queryParams['modLoaderType'] = $software === Software::Forge ? 1 : 4;
            }

            $response = $this->client()->get($this->baseUrl.'mods/'.$modId.'/files', $queryParams);

            $response->throw();

            return $response->json('data', []);
        } catch (RequestException $e) {
            Log::error('CurseForge API error getting mod files', [
                'mod_id' => $modId,
                'game_version' => $gameVersion,
                'software' => $software?->value,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get a specific mod file by ID.
     */
    public function getModFile(int $modId, int $fileId): ?array
    {
        try {
            $response = $this->client()->get($this->baseUrl.'mods/'.$modId.'/files/'.$fileId);

            $response->throw();

            return $response->json('data');
        } catch (RequestException $e) {
            Log::error('CurseForge API error getting mod file', [
                'mod_id' => $modId,
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Search for mods with various filters.
     */
    public function searchMods(array $filters = []): array
    {
        try {
            $queryParams = array_merge([
                'gameId' => $this->minecraftGameId,
                'classId' => $this->minecraftModsClassId,
            ], $filters);

            $response = $this->client()->get($this->baseUrl.'mods/search', $queryParams);

            $response->throw();

            return $response->json('data', []);
        } catch (RequestException $e) {
            Log::error('CurseForge API error searching mods', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get the latest file for a mod matching the game version and software.
     */
    public function getLatestModFile(int $modId, string $gameVersion, Software $software): ?array
    {
        $files = $this->getModFiles($modId, $gameVersion, $software);

        if (empty($files)) {
            return null;
        }

        // Files are typically returned in descending order (newest first)
        // Return the first file (latest)
        return $files[0];
    }

    /**
     * Get mod dependencies for a file.
     *
     * @param  array  $fileData  The file data from getModFile or getModFiles
     * @return array Array of dependency mod IDs with their relation types
     */
    public function getFileDependencies(array $fileData): array
    {
        $dependencies = $fileData['dependencies'] ?? [];

        $result = [
            'required' => [],
            'optional' => [],
            'embedded' => [],
        ];

        foreach ($dependencies as $dependency) {
            $modId = $dependency['modId'] ?? null;
            $relationType = $dependency['relationType'] ?? null;

            if (! $modId) {
                continue;
            }

            // Relation types: 1 = EmbeddedLibrary, 2 = OptionalDependency, 3 = RequiredDependency
            match ($relationType) {
                1 => $result['embedded'][] = $modId,
                2 => $result['optional'][] = $modId,
                3 => $result['required'][] = $modId,
                default => null,
            };
        }

        return $result;
    }
}
