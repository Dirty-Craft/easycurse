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

    /**
     * Get the download URL for a mod file.
     *
     * @param  int  $fileId  The CurseForge file ID
     * @param  string|null  $fileName  Optional file name for constructing URL if not in API response
     * @return string|null The direct download URL or null if unavailable
     */
    public function getFileDownloadUrl(int $fileId, ?string $fileName = null): ?string
    {
        try {
            // CurseForge CDN URL format: https://edge.forgecdn.net/files/{first4}/{second4}/{filename}
            // File ID is split: first 4 digits, then next 4 digits (padded if needed)
            // Example: File ID 12345678 -> files/1234/5678/filename.jar
            // Example: File ID 123 -> files/0123/0000/filename.jar
            // Example: File ID 123456789 -> files/1234/5678/filename.jar (takes first 8 digits)

            $fileIdStr = (string) $fileId;

            // Pad to at least 8 digits for splitting
            $fileIdPadded = str_pad($fileIdStr, 8, '0', STR_PAD_LEFT);

            // Split into two 4-digit parts
            $firstPart = substr($fileIdPadded, 0, 4);
            $secondPart = substr($fileIdPadded, 4, 4);

            // If we have a filename, use it; otherwise use a generic name
            $filename = $fileName ?? "file-{$fileId}.jar";

            return "https://edge.forgecdn.net/files/{$firstPart}/{$secondPart}/{$filename}";
        } catch (\Exception $e) {
            Log::error('CurseForge error generating download URL', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get the download URL from CurseForge API.
     *
     * @param  int  $fileId  The CurseForge file ID
     * @return string|null The download URL or null if unavailable
     */
    public function getFileDownloadUrlFromApi(int $fileId): ?string
    {
        try {
            // Try to get download URL from CurseForge API files endpoint
            // This endpoint might return a URL that works better with CORS
            $response = $this->client()->get($this->baseUrl.'files/'.$fileId.'/download-url');

            if ($response->successful()) {
                $data = $response->json('data');
                $url = $data['url'] ?? null;

                if ($url) {
                    Log::debug('Got download URL from API endpoint', [
                        'file_id' => $fileId,
                        'url' => $url,
                    ]);

                    return $url;
                }
            }
        } catch (\Exception $e) {
            Log::debug('CurseForge download URL endpoint not available or failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Get download information for a mod file (including URL and filename).
     *
     * @param  int  $modId  The CurseForge mod ID
     * @param  int  $fileId  The CurseForge file ID
     * @return array|null Array with 'url' and 'filename' keys, or null if unavailable
     */
    public function getFileDownloadInfo(int $modId, int $fileId): ?array
    {
        try {
            $file = $this->getModFile($modId, $fileId);

            if (! $file) {
                Log::warning('CurseForge file not found', [
                    'mod_id' => $modId,
                    'file_id' => $fileId,
                ]);

                return null;
            }

            $fileName = $file['fileName'] ?? null;

            // Try multiple methods to get download URL
            $downloadUrl = null;

            // Method 1: Try to get download URL from files endpoint first (might have better CORS support)
            $downloadUrl = $this->getFileDownloadUrlFromApi($fileId);

            // Method 2: Check if downloadUrl is provided in the API response
            if (! $downloadUrl) {
                $downloadUrl = $file['downloadUrl'] ?? null;
            }

            // Method 3: Construct CDN URL as fallback
            if (! $downloadUrl) {
                $downloadUrl = $this->getFileDownloadUrl($fileId, $fileName);
            }

            // Log the file structure for debugging
            Log::debug('CurseForge file data', [
                'mod_id' => $modId,
                'file_id' => $fileId,
                'has_downloadUrl_in_file' => isset($file['downloadUrl']),
                'downloadUrl' => $downloadUrl,
                'fileName' => $fileName,
            ]);

            if (! $downloadUrl) {
                Log::warning('CurseForge download URL not available', [
                    'mod_id' => $modId,
                    'file_id' => $fileId,
                ]);

                return null;
            }

            return [
                'url' => $downloadUrl,
                'filename' => $fileName ?? "file-{$fileId}.jar",
            ];
        } catch (\Exception $e) {
            Log::error('CurseForge error getting file download info', [
                'mod_id' => $modId,
                'file_id' => $fileId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
