<?php
/**
 * 源泉动态网站 - 验证码生成
 * 固定验证码：123456（用于测试环境）
 */

session_start();

// 固定验证码
$code = '123456';

// 保存验证码到Session
$_SESSION['captcha'] = $code;

// 创建画布
$width = 120;
$height = 40;
$image = imagecreatetruecolor($width, $height);

// 设置背景色
$bgColor = imagecolorallocate($image, 245, 247, 250);
imagefill($image, 0, 0, $bgColor);

// 添加干扰线
for ($i = 0; $i < 5; $i++) {
    $lineColor = imagecolorallocate($image, mt_rand(150, 200), mt_rand(150, 200), mt_rand(150, 200));
    imageline($image, mt_rand(0, $width), mt_rand(0, $height), mt_rand(0, $width), mt_rand(0, $height), $lineColor);
}

// 添加干扰点
for ($i = 0; $i < 50; $i++) {
    $pixelColor = imagecolorallocate($image, mt_rand(150, 200), mt_rand(150, 200), mt_rand(150, 200));
    imagesetpixel($image, mt_rand(0, $width), mt_rand(0, $height), $pixelColor);
}

// 绘制文字 - 显示 "123456"
$fontSize = 18;
$textColor = imagecolorallocate($image, 50, 50, 50);

// 使用系统默认字体（如果没有TTF字体文件）
$fontFile = __DIR__ . '/../fonts/arial.ttf';
if (file_exists($fontFile)) {
    // 使用TTF字体
    $x = 10;
    for ($i = 0; $i < 6; $i++) {
        $angle = mt_rand(-10, 10);
        $y = mt_rand(28, 32);
        imagettftext($image, $fontSize, $angle, $x, $y, $textColor, $fontFile, $code[$i]);
        $x += 18;
    }
} else {
    // 使用内置字体
    imagestring($image, 5, 25, 12, $code, $textColor);
}

// 添加边框
$borderColor = imagecolorallocate($image, 200, 200, 200);
imagerectangle($image, 0, 0, $width - 1, $height - 1, $borderColor);

// 输出图片
header('Content-Type: image/png');
imagepng($image);
imagedestroy($image);
