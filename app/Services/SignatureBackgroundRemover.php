<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SignatureBackgroundRemover
{
    public function store(UploadedFile $file, int $userId): string
    {
        $size = @getimagesize($file->getRealPath());
        if (! $size || $size[0] * $size[1] > 4000000) {
            throw ValidationException::withMessages(['signature' => 'Signature must be at most 4 megapixels. / Tandatangan mesti kurang daripada 4 megapiksel.']);
        }
        $source = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $source) {
            throw ValidationException::withMessages(['signature' => 'Unable to read the signature image. / Gambar tandatangan tidak dapat dibaca.']);
        }
        $output = imagecreatetruecolor($size[0], $size[1]);
        imagealphablending($output, false);
        imagesavealpha($output, true);
        for ($y = 0; $y < $size[1]; $y++) {
            for ($x = 0; $x < $size[0]; $x++) {
                $rgba = imagecolorsforindex($source, imagecolorat($source, $x, $y));
                $lightestInk = min($rgba['red'], $rgba['green'], $rgba['blue']);
                $backgroundAlpha = max(0, min(127, (int) round(($lightestInk - 210) * 127 / 35)));
                $alpha = max($rgba['alpha'], $backgroundAlpha);
                $color = imagecolorallocatealpha($output, $rgba['red'], $rgba['green'], $rgba['blue'], $alpha);
                imagesetpixel($output, $x, $y, $color);
            }
        }
        ob_start();
        $success = imagepng($output);
        $png = ob_get_clean();
        imagedestroy($source);
        imagedestroy($output);
        $path = 'profiles/'.$userId.'/'.Str::uuid().'.png';
        if (! $success || $png === false || ! Storage::disk('local')->put($path, $png)) {
            throw ValidationException::withMessages(['signature' => 'Unable to save the signature. / Tandatangan tidak dapat disimpan.']);
        }

        return $path;
    }
}
