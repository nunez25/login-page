<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Hash a captcha answer with a per-session salt (case-insensitive).
 */
function captcha_hash(string $answer): string
{
    if (empty($_SESSION['captcha_salt'])) {
        $_SESSION['captcha_salt'] = bin2hex(random_bytes(16));
    }
    return hash_hmac('sha256', strtoupper(trim($answer)), $_SESSION['captcha_salt']);
}

/**
 * Create a new 5-6 char code, store only its hash + timestamp, return the plain code.
 */
function captcha_generate(): string
{
    start_secure_session();
    $max  = strlen(CAPTCHA_CHARSET) - 1;
    $len  = random_int(5, 6);
    $code = '';
    for ($i = 0; $i < $len; $i++) {
        $code .= CAPTCHA_CHARSET[random_int(0, $max)];
    }
    $_SESSION['captcha'] = ['hash' => captcha_hash($code), 'created' => time()];
    return $code;
}

/**
 * Check an answer. Single use: the stored captcha is discarded on every call.
 */
function captcha_verify(string $answer): bool
{
    start_secure_session();
    $stored = $_SESSION['captcha'] ?? null;
    unset($_SESSION['captcha']);

    if (!is_array($stored) || !is_string($stored['hash'] ?? null) ||
        !is_int($stored['created'] ?? null) || !is_string($_SESSION['captcha_salt'] ?? null) ||
        $answer === '' || strlen($answer) > 10) {
        return false;
    }
    $age = time() - $stored['created'];
    if ($age < 0 || $age > CAPTCHA_TTL_SECONDS) {
        return false;
    }
    return hash_equals($stored['hash'], captcha_hash($answer));
}

/**
 * Render a code as a distorted PNG (noise lines, dots, rotated glyphs, sine warp).
 */
function captcha_render_png(string $code): string
{
    $w = 200;
    $h = 60;
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, imagecolorallocate($img, 245, 245, 245));

    for ($i = 0; $i < 6; $i++) {
        $c = imagecolorallocate($img, random_int(120, 200), random_int(120, 200), random_int(120, 200));
        imagesetthickness($img, random_int(1, 2));
        imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $c);
    }
    for ($i = 0; $i < 300; $i++) {
        $c = imagecolorallocate($img, random_int(100, 220), random_int(100, 220), random_int(100, 220));
        imagesetpixel($img, random_int(0, $w - 1), random_int(0, $h - 1), $c);
    }

    $useTtf = is_file(CAPTCHA_FONT) && function_exists('imagettftext');
    $step = ($w - 20) / strlen($code);
    foreach (str_split($code) as $i => $ch) {
        $c = imagecolorallocate($img, random_int(10, 80), random_int(10, 80), random_int(10, 80));
        $x = (int) (12 + $i * $step + random_int(-2, 2));
        if ($useTtf) {
            imagettftext($img, random_int(22, 26), random_int(-20, 20), $x, random_int(38, 46), $c, CAPTCHA_FONT, $ch);
        } else {
            imagestring($img, 5, $x, random_int(18, 26), $ch, $c);
        }
    }

    // Sine-wave vertical warp
    $out = imagecreatetruecolor($w, $h);
    imagefill($out, 0, 0, imagecolorallocate($out, 245, 245, 245));
    $amp = random_int(2, 4);
    $period = random_int(25, 40);
    $phase = random_int(0, 100) / 10;
    for ($x = 0; $x < $w; $x++) {
        $dy = (int) round($amp * sin($x / $period + $phase));
        imagecopy($out, $img, $x, $dy, $x, 0, 1, $h);
    }

    // Two lines over the text to defeat simple segmentation
    for ($i = 0; $i < 2; $i++) {
        $c = imagecolorallocate($out, random_int(60, 140), random_int(60, 140), random_int(60, 140));
        imageline($out, 0, random_int(10, $h - 10), $w, random_int(10, $h - 10), $c);
    }

    ob_start();
    imagepng($out);
    imagedestroy($img);
    imagedestroy($out);
    return (string) ob_get_clean();
}
