<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class ImageStorage
{
    public function save(?UploadedFile $file, bool $avatar = false): ?string
    {
        if (! $file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (! $file->isValid() || $file->getSize() > 2 * 1024 * 1024) {
            throw new InvalidArgumentException('Choose a valid image no larger than 2 MB.');
        }
        $info = @getimagesize($file->getRealPath());
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new InvalidArgumentException('Only genuine JPEG, PNG, or WebP images are allowed.');
        }
        if ($info[0] > 4000 || $info[1] > 4000 || $info[0] * $info[1] > 12000000) {
            throw new InvalidArgumentException('Use an image up to 4000 pixels per side and 12 megapixels.');
        }
        $source = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getRealPath()),
            IMAGETYPE_PNG => @imagecreatefrompng($file->getRealPath()),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file->getRealPath()),
        };
        if (! $source) {
            throw new InvalidArgumentException('This image could not be decoded.');
        }
        if ($avatar) {
            $side = min($info[0], $info[1]);
            $width = $height = 256;
            $sx = (int) (($info[0] - $side) / 2);
            $sy = (int) (($info[1] - $side) / 2);
            $sw = $sh = $side;
        } else {
            $ratio = min(1, 800 / max($info[0], $info[1]));
            $width = max(1, (int) round($info[0] * $ratio));
            $height = max(1, (int) round($info[1] * $ratio));
            $sx = $sy = 0;
            $sw = $info[0];
            $sh = $info[1];
        }
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, $sx, $sy, $width, $height, $sw, $sh);
        $directory = storage_path('app/private/uploads').DIRECTORY_SEPARATOR;
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        $name = bin2hex(random_bytes(16)).'.jpg';
        $saved = imagejpeg($canvas, $directory.$name, 85);
        imagedestroy($source);
        imagedestroy($canvas);
        if (! $saved) {
            throw new \RuntimeException('Unable to save the image. Check storage/app/private/uploads permissions.');
        }

        return $name;
    }

    public function delete(?string $name): void
    {
        if ($name && preg_match('/^[a-zA-Z0-9-]+\.jpg$/D', $name) && is_file(storage_path('app/private/uploads').DIRECTORY_SEPARATOR.$name)) {
            unlink(storage_path('app/private/uploads').DIRECTORY_SEPARATOR.$name);
        }
    }
}
