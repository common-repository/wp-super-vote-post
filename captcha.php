<?php

@session_start();
$postID = (int) $_REQUEST['postid'];
if (!isset($_SESSION['CAPCHA_' . $postID])) {
    $_SESSION['CAPCHA_' . $postID] = chr(rand(65, 78)) . chr(rand(65, 78)) . chr(rand(65, 78)) . rand(1, 9) . rand(1, 9) . rand(1, 0);
}

$texto = $_SESSION['CAPCHA_' . $postID];
$im = imagecreate(200, 50);

// transparent color
$transparent_color = imagecolorallocate($im, 255, 255, 255);
$cor = imagecolorallocate($im, 0, 51, 132);


$pos = 0;

$corLinha = imagecolorallocate($im, 0, 0, 0);
for ($index = 0; $index < 200; $index++) {
    imageline($im, rand(0, 300), rand(0, 300), rand(0, 300), rand(0, 300), imagecolorallocate($im, rand(0, 255), rand(0, 255), rand(0, 255)));
}
imagettftext($im, 30, 0, 30, 40, $cor, dirname(__FILE__) . DIRECTORY_SEPARATOR . "fontes/Avgardn.ttf", $texto);

//SETA A COR TRANSPARENTE
imagecolortransparent($im, $transparent_color);

header("Content-type: image/png");
imagepng($im);
imagedestroy($im);
?>