<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class BusinessImageController extends Controller
{
    public function store(Request $request, Business $business)
    {
        $request->validate(['image' => ['required', 'image', 'max:8192']]);
        $file = $request->file('image');
        $disk = 'public';

        // 1️⃣ Delete old images first (if they exist)
        $this->deleteOldIfExists($business, $disk);

        // 2️⃣ Build folders
        $dateFolder = date('Y') . '/' . date('m') . '/' . date('d');
        $baseFolder = "images/businesses/{$dateFolder}/{$business->id}";
        $ext = strtolower($file->getClientOriginalExtension());

        // 3️⃣ Save new original
        $origPath = $file->storeAs($baseFolder, "original.{$ext}", $disk);
        $absOrig  = Storage::disk($disk)->path($origPath);

        // 4️⃣ Generate variants (preserve aspect ratio)
        $thumbRel = "{$baseFolder}/thumb.webp";
        $cardRel  = "{$baseFolder}/card.webp";

        $this->makeVariant($absOrig, $disk, $thumbRel, 320, 320, 70);
        $this->makeVariant($absOrig, $disk, $cardRel, 640, 400, 75);

        // 5️⃣ Update DB
        $business->update([
            'image_original'   => $origPath,
            'image_thumb_webp' => $thumbRel,
            'image_card_webp'  => $cardRel,
        ]);

        return response()->json([
            'message'  => 'Image replaced and variants regenerated',
            'original' => Storage::url($origPath),
            'thumb'    => Storage::url($thumbRel),
            'card'     => Storage::url($cardRel),
        ], 201);
    }


    private function makeVariant(string $absOrig, string $disk, string $rel, int $width, int $height, int $quality): void
    {
        // Prepare destination absolute path
        $dest = \Illuminate\Support\Facades\Storage::disk($disk)->path($rel);
        @mkdir(dirname($dest), 0775, true);

        // Use Imagick driver if available
        $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Imagick\Driver());

        // Read and proportionally scale down to fit within target box
        $img = $manager->read($absOrig)
            // scaleDown keeps aspect ratio (like "object-fit: contain")
            ->scaleDown($width, $height);

        // Save as WebP (or fallback)
        if ($this->webpSupported()) {
            $img->toWebp($quality)->save($dest);
        } else {
            $destJpg = preg_replace('/\.webp$/', '.jpg', $dest);
            @mkdir(dirname($destJpg), 0775, true);
            $img->toJpeg($quality)->save($destJpg);
        }
    }


    private function webpSupported(): bool
    {
        if (!extension_loaded('gd')) return false;
        $info = @gd_info();
        return is_array($info) && !empty($info['WebP Support']);
    }

    private function deleteOldIfExists(Business $business, string $disk): void
    {
        // Delete previously stored files (if any)
        $paths = array_filter([
            $business->image_original,
            $business->image_thumb_webp,
            $business->image_card_webp,
        ]);
        if (!empty($paths)) {
            Storage::disk($disk)->delete($paths);
        }
    }
}
