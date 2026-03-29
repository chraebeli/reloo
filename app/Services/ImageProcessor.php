<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class ImageProcessor
{
    private const MAX_DIMENSION = 500;

    /**
     * @return array{mime:string,width:int,height:int}
     */
    public function inspectUploadedImage(string $tmpPath): array
    {
        if (!is_file($tmpPath)) {
            throw new RuntimeException('Ungültige Bildquelle.');
        }

        $imageInfo = @getimagesize($tmpPath);
        if (!is_array($imageInfo)) {
            throw new RuntimeException('Datei ist kein gültiges Bild.');
        }

        $mime = (string) ($imageInfo['mime'] ?? '');
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('Nur JPG, PNG und WEBP sind erlaubt.');
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);

        if ($width < 80 || $height < 80) {
            throw new RuntimeException('Bildauflösung zu klein (mind. 80x80 Pixel).');
        }

        return ['mime' => $mime, 'width' => $width, 'height' => $height];
    }

    /**
     * @return array{binary:string,mime:string,extension:string,width:int,height:int}
     */
    public function optimize(string $tmpPath, string $mime): array
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('GD ist nicht verfügbar.');
        }

        $source = $this->createImageResource($tmpPath, $mime);
        if (!$source) {
            throw new RuntimeException('Bild konnte nicht dekodiert werden.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        [$targetWidth, $targetHeight] = $this->targetDimensions($sourceWidth, $sourceHeight);

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($target === false) {
            imagedestroy($source);
            throw new RuntimeException('Bildverarbeitung fehlgeschlagen.');
        }

        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        $copied = imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        imagedestroy($source);

        if ($copied === false) {
            imagedestroy($target);
            throw new RuntimeException('Bild konnte nicht skaliert werden.');
        }

        $binary = $this->encodeImage($target, $mime);
        imagedestroy($target);

        return [
            'binary' => $binary,
            'mime' => $mime,
            'extension' => $this->extensionForMime($mime),
            'width' => $targetWidth,
            'height' => $targetHeight,
        ];
    }

    /**
     * @return resource|\GdImage|false
     */
    private function createImageResource(string $tmpPath, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmpPath),
            'image/png' => @imagecreatefrompng($tmpPath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : false,
            default => false,
        };
    }

    private function encodeImage(\GdImage $image, string $mime): string
    {
        ob_start();

        $encoded = match ($mime) {
            'image/jpeg' => imagejpeg($image, null, 82),
            'image/png' => imagepng($image, null, 7),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, null, 82) : false,
            default => false,
        };

        $content = ob_get_clean();

        if ($encoded === false || !is_string($content) || $content === '') {
            throw new RuntimeException('Bild konnte nicht gespeichert werden.');
        }

        return $content;
    }

    /** @return array{int,int} */
    private function targetDimensions(int $width, int $height): array
    {
        if ($width <= self::MAX_DIMENSION && $height <= self::MAX_DIMENSION) {
            return [$width, $height];
        }

        $scale = min(self::MAX_DIMENSION / $width, self::MAX_DIMENSION / $height);

        return [
            max(1, (int) floor($width * $scale)),
            max(1, (int) floor($height * $scale)),
        ];
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
    }
}
