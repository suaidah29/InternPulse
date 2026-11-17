<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignatureBackgroundTest extends TestCase
{
    use RefreshDatabase;

    public function test_signature_upload_removes_white_background_and_preserves_dark_ink(): void
    {
        Storage::fake('local');
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $image = imagecreatetruecolor(10, 10);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagesetpixel($image, 5, 5, imagecolorallocate($image, 0, 0, 0));
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        $file = UploadedFile::fake()->createWithContent('signature.png', $png);
        $this->actingAs($supervisor)->post(route('profile.update'), ['name' => $supervisor->name, 'email' => $supervisor->email, 'signature' => $file])->assertRedirect(route('profile.edit'));
        $path = $supervisor->fresh()->signature_path;
        $result = imagecreatefromstring(Storage::disk('local')->get($path));
        $this->assertSame(127, imagecolorsforindex($result, imagecolorat($result, 0, 0))['alpha']);
        $this->assertSame(0, imagecolorsforindex($result, imagecolorat($result, 5, 5))['alpha']);
    }
}
