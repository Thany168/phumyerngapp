<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /**
     * Streams asset binaries directly to HTML <img> tags.
     * Smart enough to handle both Cloudinary links and old local storage paths!
     */
    public function stream(Request $request)
    {
        $path = $request->query('path');

        if (!$path) {
            return $this->serveLocalFallback();
        }

        // 1️⃣ 🎯 CLOUDINARY PASSTHROUGH: If it's already a full web URL, redirect right to it!
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return redirect()->away($path);
        }

        // Clean folder prefix structures for old local assets
        $cleanPath = ltrim(str_replace(['storage/', 'public/'], '', $path), '/');
        $filenameOnly = basename($cleanPath);

        $finalDiskPath = null;

        // Search through local directories
        if (Storage::disk('public')->exists($cleanPath)) {
            $finalDiskPath = $cleanPath;
        } elseif (Storage::disk('public')->exists('products/' . $filenameOnly)) {
            $finalDiskPath = 'products/' . $filenameOnly;
        } elseif (Storage::disk('public')->exists($filenameOnly)) {
            $finalDiskPath = $filenameOnly;
        }

        // 2️⃣ 🎯 LOCAL STORAGE FLOW: Stream raw binary bytes directly to the <img> src attribute
        if ($finalDiskPath) {
            $fileContents = Storage::disk('public')->get($finalDiskPath);
            $mimeType = Storage::disk('public')->mimeType($finalDiskPath);

            return response($fileContents, 200)->withHeaders([
                'Content-Type' => $mimeType,
                'Cache-Control' => 'public, max-age=86400',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS'
            ]);
        }

        // 3️⃣ 🎯 FALLBACK FLOW: File doesn't exist anywhere, serve a bulletproof placeholder
        return $this->serveLocalFallback();
    }

    /**
     * Returns a built-in light grey placeholder block image.
     * Completely local—requires NO internet download, making it impossible to crash!
     */
    private function serveLocalFallback()
    {
        // A clean, valid 1x1 light gray pixel PNG to keep the UI looking perfect
        $transparentPngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $binary = base64_decode($transparentPngBase64);

        return response($binary, 200)->withHeaders([
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS'
        ]);
    }
}
