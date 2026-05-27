<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;

class CloudinaryService
{
    private Cloudinary $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key' => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
            'url' => [
                'secure' => true
            ]
        ]);
    }

    public function uploadToCloudinary(string $folder, UploadedFile $file): array
    {
        $result = $this->cloudinary->uploadApi()->upload(
            $file->getRealPath(),
            [
                'folder' => "JobPortal/{$folder}",
                'resource_type' => 'auto',
            ]
        );

        return [
            'url' => $result['secure_url'],
            'publicId' => $result['public_id'],
        ];
    }

    public function cleanupCloudinary(array $publicIds): void
    {
        foreach ($publicIds as $publicId) {
            $this->cloudinary->uploadApi()->destroy($publicId);
        }
    }
}