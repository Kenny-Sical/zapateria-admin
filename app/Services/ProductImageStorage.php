<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductImageStorage
{
    /**
     * Stores a product image locally for WooCommerce to sideload.
     *
     * @return array ['path' => string, 'url' => string]
     */
    public function store(UploadedFile $image, string $sku): array
    {
        $year = date('Y');
        $month = date('m');
        $targetDir = "product-images/{$year}/{$month}";

        $cleanSku = preg_replace('/[^A-Za-z0-9\-]/', '', $sku);
        if (empty($cleanSku)) {
            $cleanSku = 'prod';
        }
        $fileName = 'sku_'.$cleanSku.'_'.time().'.webp';

        $sourceImage = null;
        $mime = $image->getMimeType();
        switch ($mime) {
            case 'image/jpeg':
                $sourceImage = @imagecreatefromjpeg($image->getPathname());
                break;
            case 'image/png':
                $sourceImage = @imagecreatefrompng($image->getPathname());
                if ($sourceImage) {
                    imagepalettetotruecolor($sourceImage);
                    imagealphablending($sourceImage, true);
                    imagesavealpha($sourceImage, true);
                }
                break;
            case 'image/webp':
                $sourceImage = @imagecreatefromwebp($image->getPathname());
                break;
            case 'image/gif':
                $sourceImage = @imagecreatefromgif($image->getPathname());
                break;
        }

        if ($sourceImage) {
            ob_start();
            imagewebp($sourceImage, null, 85);
            $bytes = ob_get_clean();
            imagedestroy($sourceImage);

            $path = "{$targetDir}/{$fileName}";
            Storage::disk('public')->put($path, $bytes);
        } else {
            $extension = $image->getClientOriginalExtension() ?: 'jpg';
            $fileName = 'sku_'.$cleanSku.'_'.time().'.'.$extension;
            $path = Storage::disk('public')->putFileAs($targetDir, $image, $fileName);
        }

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ];
    }

    /**
     * Deletes the temporary product image from local storage.
     */
    public function delete(string $path): void
    {
        Storage::disk('public')->delete($path);
    }
}
