<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\Cloudinary\Cloudinary::class, function ($app) {
            $config = $app['config']->get('filesystems.disks.cloudinary');

            if (!$config) {
                $config = [
                    'cloud' => config('cloudinary.cloud_name'),
                    'key' => config('cloudinary.api_key'),
                    'secret' => config('cloudinary.api_secret'),
                ];
            }

            $cloudinaryUrl = env('CLOUDINARY_URL');

            if ($cloudinaryUrl) {
                return new \App\Services\CloudinaryLocalBypass($cloudinaryUrl);
            }

            return new \App\Services\CloudinaryLocalBypass([
                'cloud' => [
                    'cloud_name' => $config['cloud'] ?? null,
                    'api_key' => $config['key'] ?? null,
                    'api_secret' => $config['secret'] ?? null,
                ],
                'url' => [
                    'secure' => $config['secure'] ?? false,
                ],
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        if (config('app.env') !== 'local' || isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            URL::forceScheme('https');
        }
    }
}
