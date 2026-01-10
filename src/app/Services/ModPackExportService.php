<?php

namespace App\Services;

use App\Models\ModPack;
use App\Models\ModPackItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ModPackExportService
{
    private ModService $modService;

    public function __construct(?ModService $modService = null)
    {
        $this->modService = $modService ?? new ModService;
    }

    /**
     * Export modpack as CurseForge format (.zip with manifest.json).
     */
    public function exportAsCurseForge(ModPack $modPack): string
    {
        $tempDir = $this->getTempDirectory();
        $zipPath = $tempDir.'/'.'modpack-'.$modPack->id.'-curseforge.zip';
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create ZIP archive');
        }

        try {
            // Create manifest.json
            $manifest = $this->buildCurseForgeManifest($modPack);
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Create overrides directory structure
            $zip->addEmptyDir('overrides');
            $zip->addEmptyDir('overrides/mods');

            // Download and add mod files
            $downloadedFiles = [];
            foreach ($modPack->items as $item) {
                $fileInfo = $this->downloadModFile($item, $tempDir);
                if ($fileInfo) {
                    $zip->addFile($fileInfo['path'], 'overrides/mods/'.$fileInfo['filename']);
                    $downloadedFiles[] = $fileInfo['path'];
                } else {
                    // If download failed, skip but note in manifest if it's a CurseForge mod
                    if ($item->source === 'curseforge' && $item->curseforge_mod_id && $item->curseforge_file_id) {
                        // Already added to manifest, so it's OK
                        Log::warning('Failed to download mod file for CurseForge export', [
                            'mod_pack_id' => $modPack->id,
                            'item_id' => $item->id,
                            'mod_name' => $item->mod_name,
                        ]);
                    }
                }
            }

            $zip->close();

            // Clean up downloaded files
            foreach ($downloadedFiles as $file) {
                @unlink($file);
            }

            return $zipPath;
        } catch (\Exception $e) {
            $zip->close();
            @unlink($zipPath);
            throw $e;
        }
    }

    /**
     * Export modpack as MultiMC instance format.
     */
    public function exportAsMultiMC(ModPack $modPack): string
    {
        $tempDir = $this->getTempDirectory();
        $zipPath = $tempDir.'/'.'modpack-'.$modPack->id.'-multimc.zip';
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create ZIP archive');
        }

        try {
            // Create instance.cfg
            $instanceConfig = $this->buildMultiMCInstanceConfig($modPack);
            $zip->addFromString('instance.cfg', $instanceConfig);

            // Create mmc-pack.json
            $mmcPack = $this->buildMultiMCPackJson($modPack);
            $zip->addFromString('mmc-pack.json', json_encode($mmcPack, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Create .minecraft directory structure
            $zip->addEmptyDir('.minecraft');
            $zip->addEmptyDir('.minecraft/mods');
            $zip->addEmptyDir('.minecraft/config');

            // Download and add mod files
            $downloadedFiles = [];
            foreach ($modPack->items as $item) {
                $fileInfo = $this->downloadModFile($item, $tempDir);
                if ($fileInfo) {
                    $zip->addFile($fileInfo['path'], '.minecraft/mods/'.$fileInfo['filename']);
                    $downloadedFiles[] = $fileInfo['path'];
                }
            }

            $zip->close();

            // Clean up downloaded files
            foreach ($downloadedFiles as $file) {
                @unlink($file);
            }

            return $zipPath;
        } catch (\Exception $e) {
            $zip->close();
            @unlink($zipPath);
            throw $e;
        }
    }

    /**
     * Export modpack as Modrinth format (.mrpack).
     */
    public function exportAsModrinth(ModPack $modPack): string
    {
        $tempDir = $this->getTempDirectory();
        $zipPath = $tempDir.'/'.'modpack-'.$modPack->id.'-modrinth.mrpack';
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create ZIP archive');
        }

        try {
            // Download files and build index
            $indexFiles = [];
            $downloadedFiles = [];

            foreach ($modPack->items as $item) {
                $fileInfo = $this->downloadModFile($item, $tempDir, true); // Calculate hashes
                if ($fileInfo) {
                    $fileData = [
                        'path' => 'mods/'.$fileInfo['filename'],
                        'hashes' => [
                            'sha1' => $fileInfo['sha1'] ?? null,
                            'sha512' => $fileInfo['sha512'] ?? null,
                        ],
                        'env' => [
                            'client' => 'required',
                            'server' => 'required',
                        ],
                        'downloads' => [$fileInfo['download_url']],
                        'fileSize' => $fileInfo['size'] ?? 0,
                    ];

                    // Remove null hashes
                    $fileData['hashes'] = array_filter($fileData['hashes']);

                    $indexFiles[] = $fileData;
                    $downloadedFiles[] = $fileInfo;
                }
            }

            // Create modrinth.index.json
            $index = [
                'formatVersion' => 1,
                'game' => 'minecraft',
                'versionId' => '1.0.0',
                'name' => $modPack->name,
                'summary' => $modPack->description ?? '',
                'files' => $indexFiles,
                'dependencies' => [
                    'minecraft' => $modPack->minecraft_version,
                    $this->mapLoaderToModrinth($modPack->software) => $this->extractLoaderVersion($modPack->software, $modPack->minecraft_version),
                ],
            ];

            // Remove empty dependency values
            $index['dependencies'] = array_filter($index['dependencies']);

            $zip->addFromString('modrinth.index.json', json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Add files to overrides (optional, but some tools expect it)
            if (! empty($downloadedFiles)) {
                $zip->addEmptyDir('overrides');
                $zip->addEmptyDir('overrides/mods');

                foreach ($downloadedFiles as $fileInfo) {
                    if (isset($fileInfo['path'])) {
                        $zip->addFile($fileInfo['path'], 'overrides/mods/'.$fileInfo['filename']);
                    }
                }
            }

            $zip->close();

            // Clean up downloaded files
            foreach ($downloadedFiles as $fileInfo) {
                if (isset($fileInfo['path'])) {
                    @unlink($fileInfo['path']);
                }
            }

            return $zipPath;
        } catch (\Exception $e) {
            $zip->close();
            @unlink($zipPath);
            throw $e;
        }
    }

    /**
     * Export modpack as plain text.
     */
    public function exportAsText(ModPack $modPack): string
    {
        $lines = [];
        $lines[] = $modPack->name;
        $lines[] = str_repeat('=', strlen($modPack->name));
        $lines[] = '';
        if ($modPack->description) {
            $lines[] = 'Description: '.$modPack->description;
            $lines[] = '';
        }
        $lines[] = 'Minecraft Version: '.$modPack->minecraft_version;
        $lines[] = 'Loader: '.ucfirst($modPack->software);
        $lines[] = '';
        $lines[] = 'Mods:';
        $lines[] = str_repeat('-', 80);
        $lines[] = sprintf('%-40s | %-15s | %-12s | %s', 'Mod Name', 'Version', 'Source', 'URL');
        $lines[] = str_repeat('-', 80);

        foreach ($modPack->items as $item) {
            $url = $this->getModUrl($item);
            $lines[] = sprintf('%-40s | %-15s | %-12s | %s', $item->mod_name, $item->mod_version, ucfirst($item->source ?? 'Unknown'), $url);
        }

        return implode("\n", $lines);
    }

    /**
     * Export modpack as CSV.
     */
    public function exportAsCsv(ModPack $modPack): string
    {
        $lines = [];
        $lines[] = '"Name","Version","Source","Platform URL"';

        foreach ($modPack->items as $item) {
            $url = $this->getModUrl($item);

            $lines[] = sprintf(
                '"%s","%s","%s","%s"',
                $this->escapeCsv($item->mod_name),
                $this->escapeCsv($item->mod_version),
                $this->escapeCsv(ucfirst($item->source ?? 'Unknown')),
                $this->escapeCsv($url)
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Build CurseForge manifest.json structure.
     */
    private function buildCurseForgeManifest(ModPack $modPack): array
    {
        $loaderVersion = $this->extractLoaderVersion($modPack->software, $modPack->minecraft_version);
        $loaderId = $modPack->software.'-'.$loaderVersion;

        $manifest = [
            'minecraft' => [
                'version' => $modPack->minecraft_version,
                'modLoaders' => [
                    [
                        'id' => $loaderId,
                        'primary' => true,
                    ],
                ],
            ],
            'manifestType' => 'minecraftModpack',
            'manifestVersion' => 1,
            'name' => $modPack->name,
            'version' => '1.0.0',
            'author' => $modPack->user->name ?? 'Unknown',
            'files' => [],
        ];

        // Add CurseForge mods to files array
        foreach ($modPack->items as $item) {
            if ($item->source === 'curseforge' && $item->curseforge_mod_id && $item->curseforge_file_id) {
                $manifest['files'][] = [
                    'projectID' => (int) $item->curseforge_mod_id,
                    'fileID' => (int) $item->curseforge_file_id,
                    'required' => true,
                ];
            }
        }

        return $manifest;
    }

    /**
     * Build MultiMC instance.cfg content.
     */
    private function buildMultiMCInstanceConfig(ModPack $modPack): string
    {
        return "InstanceType=OneSix\n".
            "IntendedVersion={$modPack->minecraft_version}\n".
            "name={$modPack->name}\n";
    }

    /**
     * Build MultiMC mmc-pack.json structure.
     */
    private function buildMultiMCPackJson(ModPack $modPack): array
    {
        $loaderVersion = $this->extractLoaderVersion($modPack->software, $modPack->minecraft_version);
        $loaderUid = $this->mapLoaderToMultiMC($modPack->software);

        $components = [
            [
                'cachedName' => 'Minecraft',
                'cachedVersion' => $modPack->minecraft_version,
                'cachedVolatile' => true,
                'dependencyOnly' => true,
                'uid' => 'net.minecraft',
                'version' => $modPack->minecraft_version,
            ],
        ];

        if ($loaderUid) {
            $components[] = [
                'cachedName' => ucfirst($modPack->software),
                'cachedVersion' => $loaderVersion,
                'cachedVolatile' => true,
                'uid' => $loaderUid,
                'version' => $loaderVersion,
            ];
        }

        return [
            'components' => $components,
            'formatVersion' => 1,
        ];
    }

    /**
     * Download a mod file to temporary storage.
     */
    private function downloadModFile(ModPackItem $item, string $tempDir, bool $calculateHashes = false): ?array
    {
        $downloadInfo = $this->getItemDownloadInfo($item);
        if (! $downloadInfo || ! isset($downloadInfo['url'])) {
            return null;
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:108.0) Gecko/20100101 Firefox/108.0',
                ])
                ->get($downloadInfo['url']);

            if (! $response->successful()) {
                Log::warning('Failed to download mod file', [
                    'item_id' => $item->id,
                    'url' => $downloadInfo['url'],
                    'status' => $response->status(),
                ]);

                return null;
            }

            $filename = $downloadInfo['filename'] ?? basename(parse_url($downloadInfo['url'], PHP_URL_PATH));
            if (! $filename || ! preg_match('/\.jar$/', $filename)) {
                $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $item->mod_name).'.jar';
            }

            $filePath = $tempDir.'/'.uniqid().'-'.$filename;
            file_put_contents($filePath, $response->body());

            $result = [
                'path' => $filePath,
                'filename' => $filename,
                'download_url' => $downloadInfo['url'],
                'size' => filesize($filePath),
            ];

            // For Modrinth, try to use provided hashes if available
            if ($calculateHashes) {
                if (isset($downloadInfo['sha1'])) {
                    $result['sha1'] = $downloadInfo['sha1'];
                } else {
                    $result['sha1'] = hash_file('sha1', $filePath);
                }
                if (isset($downloadInfo['sha512'])) {
                    $result['sha512'] = $downloadInfo['sha512'];
                } else {
                    $result['sha512'] = hash_file('sha512', $filePath);
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Error downloading mod file', [
                'item_id' => $item->id,
                'url' => $downloadInfo['url'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get download info for a mod pack item.
     * For Modrinth, also includes file hashes from version data.
     */
    private function getItemDownloadInfo(ModPackItem $item): ?array
    {
        $source = $item->source;

        // Determine source from item data if not set
        if (! $source) {
            if ($item->curseforge_mod_id && $item->curseforge_file_id) {
                $source = 'curseforge';
            } elseif ($item->modrinth_project_id && $item->modrinth_version_id) {
                $source = 'modrinth';
            } else {
                return null;
            }
        }

        $modId = null;
        $fileId = null;

        if ($source === 'curseforge') {
            if (! $item->curseforge_mod_id || ! $item->curseforge_file_id) {
                return null;
            }
            $modId = $item->curseforge_mod_id;
            $fileId = $item->curseforge_file_id;
        } elseif ($source === 'modrinth') {
            if (! $item->modrinth_project_id || ! $item->modrinth_version_id) {
                return null;
            }
            $modId = $item->modrinth_project_id;
            $fileId = $item->modrinth_version_id;
        } else {
            return null;
        }

        $downloadInfo = $this->modService->getFileDownloadInfo($modId, $fileId, $source);

        // For Modrinth, try to get file hashes from version data
        if ($source === 'modrinth' && $downloadInfo) {
            $versionData = $this->modService->getModFile($modId, $fileId, 'modrinth');
            if ($versionData && isset($versionData['files']) && is_array($versionData['files']) && ! empty($versionData['files'])) {
                // Find the primary file or use the first one
                $primaryFile = null;
                foreach ($versionData['files'] as $file) {
                    if (isset($file['primary']) && $file['primary'] === true) {
                        $primaryFile = $file;
                        break;
                    }
                }
                if (! $primaryFile) {
                    $primaryFile = $versionData['files'][0];
                }

                if ($primaryFile) {
                    // Add hashes if available
                    if (isset($primaryFile['hashes']['sha1'])) {
                        $downloadInfo['sha1'] = $primaryFile['hashes']['sha1'];
                    }
                    if (isset($primaryFile['hashes']['sha512'])) {
                        $downloadInfo['sha512'] = $primaryFile['hashes']['sha512'];
                    }
                    // Use filename from version if not set
                    if (! isset($downloadInfo['filename']) && isset($primaryFile['filename'])) {
                        $downloadInfo['filename'] = $primaryFile['filename'];
                    }
                    // Use size if available
                    if (isset($primaryFile['size'])) {
                        $downloadInfo['size'] = $primaryFile['size'];
                    }
                }
            }
        }

        return $downloadInfo;
    }

    /**
     * Get mod URL from item.
     */
    private function getModUrl(ModPackItem $item): string
    {
        if ($item->source === 'curseforge' && $item->curseforge_slug) {
            return 'https://www.curseforge.com/minecraft/mc-mods/'.$item->curseforge_slug;
        } elseif ($item->source === 'modrinth' && $item->modrinth_slug) {
            return 'https://modrinth.com/mod/'.$item->modrinth_slug;
        } elseif ($item->curseforge_slug) {
            return 'https://www.curseforge.com/minecraft/mc-mods/'.$item->curseforge_slug;
        } elseif ($item->modrinth_slug) {
            return 'https://modrinth.com/mod/'.$item->modrinth_slug;
        }

        return '';
    }

    /**
     * Map loader to MultiMC component UID.
     */
    private function mapLoaderToMultiMC(string $software): ?string
    {
        return match ($software) {
            'forge' => 'net.minecraftforge',
            'fabric' => 'net.fabricmc.fabric-loader',
            'quilt' => 'org.quiltmc.quilt-loader',
            'neoforge' => 'net.neoforged',
            default => null,
        };
    }

    /**
     * Map loader to Modrinth dependency name.
     */
    private function mapLoaderToModrinth(string $software): string
    {
        return match ($software) {
            'neoforge' => 'neoforge',
            default => $software,
        };
    }

    /**
     * Extract loader version from software string or use generic version.
     */
    private function extractLoaderVersion(string $software, string $minecraftVersion): string
    {
        // For now, return a generic version based on Minecraft version
        // In a production system, you might want to store the exact loader version
        // or query the APIs for the recommended version
        return match ($minecraftVersion) {
            '1.20.1' => match ($software) {
                'forge' => '47.1.0',
                'fabric' => '0.14.22',
                'quilt' => '0.23.0',
                'neoforge' => '20.1.0',
                default => 'latest',
            },
            '1.19.2' => match ($software) {
                'forge' => '43.2.0',
                'fabric' => '0.14.10',
                'quilt' => '0.18.0',
                default => 'latest',
            },
            default => 'latest',
        };
    }

    /**
     * Get temporary directory for file operations.
     */
    private function getTempDirectory(): string
    {
        $tempDir = storage_path('app/temp/'.uniqid());
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        return $tempDir;
    }

    /**
     * Escape CSV field.
     */
    private function escapeCsv(string $value): string
    {
        return str_replace('"', '""', $value);
    }
}
