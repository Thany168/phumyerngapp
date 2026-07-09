<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Api\Search\SearchApi;
use Cloudinary\Api\Search\SearchFoldersApi;

class CloudinaryLocalBypass extends Cloudinary
{
    private function bypassSsl($apiInstance)
    {
        if (config('app.env') === 'local') {
            try {
                $reflection = new \ReflectionClass($apiInstance);
                
                foreach ($reflection->getProperties() as $property) {
                    if (str_contains(strtolower($property->getName()), 'client')) {
                        $property->setAccessible(true);
                        $client = $property->getValue($apiInstance);
                        if ($client && isset($client->httpClient)) {
                            $config = $client->httpClient->getConfig();
                            $config['verify'] = false;
                            $client->httpClient = new \GuzzleHttp\Client($config);
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ignore any issues during SSL bypass configuration
            }
        }
        return $apiInstance;
    }

    public function uploadApi(): UploadApi
    {
        return $this->bypassSsl(parent::uploadApi());
    }

    public function adminApi(): AdminApi
    {
        return $this->bypassSsl(parent::adminApi());
    }

    public function searchApi(): SearchApi
    {
        return $this->bypassSsl(parent::searchApi());
    }

    public function searchFoldersApi(): SearchFoldersApi
    {
        return $this->bypassSsl(parent::searchFoldersApi());
    }

    /**
     * Proxy destroy() to uploadApi()->destroy() so Cloudinary::destroy($publicId)
     * works correctly through the bypass class without throwing "undefined method".
     */
    public function destroy(string $publicId, array $options = []): array
    {
        try {
            $result = $this->uploadApi()->destroy($publicId, $options);
            return is_array($result) ? $result : [];
        } catch (\Exception $e) {
            \Log::warning('CloudinaryLocalBypass::destroy failed: ' . $e->getMessage());
            return ['result' => 'error', 'message' => $e->getMessage()];
        }
    }
}
