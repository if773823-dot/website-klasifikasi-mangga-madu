<?php

final class ImageFeatureExtractor
{
    public function extract(string $path, int $targetWidth = 256, int $targetHeight = 256): array
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('Ekstensi PHP gd belum aktif. Aktifkan gd agar sistem bisa membaca gambar.');
        }

        $info = getimagesize($path);
        if ($info === false) {
            throw new RuntimeException('File yang diunggah bukan gambar valid.');
        }

        $source = $this->createImageResource($path, $info[2]);
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled(
            $resized,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            imagesx($source),
            imagesy($source)
        );

        $totalRed = 0;
        $totalGreen = 0;
        $totalBlue = 0;
        $totalPixels = $targetWidth * $targetHeight;

        for ($y = 0; $y < $targetHeight; $y++) {
            for ($x = 0; $x < $targetWidth; $x++) {
                $rgb = imagecolorat($resized, $x, $y);
                $totalRed += ($rgb >> 16) & 0xFF;
                $totalGreen += ($rgb >> 8) & 0xFF;
                $totalBlue += $rgb & 0xFF;
            }
        }

        return [
            'mean_red' => round($totalRed / $totalPixels, 4),
            'mean_green' => round($totalGreen / $totalPixels, 4),
            'mean_blue' => round($totalBlue / $totalPixels, 4),
            'ukuran_citra' => $targetWidth . 'x' . $targetHeight,
        ];
    }

    private function createImageResource(string $path, int $imageType): GdImage
    {
        return match ($imageType) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            default => throw new RuntimeException('Format gambar belum didukung. Gunakan JPG, PNG, atau WebP.'),
        };
    }
}
