<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Reward;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LevelCardGenerator
{
    public function generate(Customer $customer, ?Reward $reward = null): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        try {
            $customer->loadMissing(['business', 'tier']);
            $width = 1200;
            $height = 630;
            $image = imagecreatetruecolor($width, $height);
            imagealphablending($image, true);
            imagesavealpha($image, true);

            $background = imagecolorallocate($image, 15, 17, 21);
            $panel = imagecolorallocate($image, 28, 31, 37);
            $gold = imagecolorallocate($image, 212, 175, 55);
            $white = imagecolorallocate($image, 244, 241, 232);
            $muted = imagecolorallocate($image, 170, 174, 184);
            imagefill($image, 0, 0, $background);
            imagefilledrectangle($image, 56, 52, 1144, 578, $panel);
            imagefilledrectangle($image, 56, 52, 72, 578, $gold);
            imagefilledellipse($image, 980, 180, 210, 210, $gold);
            imagefilledellipse($image, 980, 180, 168, 168, $background);

            imagestring($image, 5, 108, 96, strtoupper($this->safe($customer->business->name, 44)), $gold);
            imagestring($image, 5, 108, 162, $this->safe($customer->name, 38), $white);
            imagestring($image, 5, 918, 150, 'NIVEL', $muted);
            imagestring($image, 5, 960, 190, (string) $customer->level, $white);
            imagestring($image, 5, 108, 240, 'RANGO', $muted);
            imagestring($image, 5, 108, 278, strtoupper($customer->tier?->name ?? 'BRONCE'), $white);
            imagestring($image, 5, 420, 240, 'XP HISTORICO', $muted);
            imagestring($image, 5, 420, 278, number_format($customer->xp_total).' XP', $white);

            $progress = $customer->progressPercent($customer->business->loyaltyProgram?->xp_per_level ?? 100);
            imagefilledrectangle($image, 108, 362, 1020, 390, $background);
            imagefilledrectangle($image, 108, 362, 108 + (int) (912 * $progress / 100), 390, $gold);
            imagestring($image, 4, 108, 408, "PROGRESO AL SIGUIENTE NIVEL  {$progress}%", $muted);

            if ($reward) {
                imagestring($image, 4, 108, 486, 'RECOMPENSA DESBLOQUEADA', $gold);
                imagestring($image, 5, 108, 522, $this->safe($reward->name, 56), $white);
            } else {
                imagestring($image, 4, 108, 502, 'SIGUE SUMANDO EXPERIENCIA. TU PROXIMO NIVEL TE ESPERA.', $muted);
            }

            $path = 'level-cards/'.now()->format('Y/m').'/'.Str::uuid().'.png';
            ob_start();
            imagepng($image, null, 8);
            $contents = ob_get_clean();
            imagedestroy($image);
            Storage::disk('public')->put($path, $contents);

            return $path;
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function safe(string $value, int $length): string
    {
        return mb_strtoupper(mb_substr(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value, 0, $length));
    }
}
