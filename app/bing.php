<?php

require_once __DIR__ . '/function.php';
require_once APP_ROOT . '/config/config.php';

$cacheDir = APP_ROOT . $config['path'] . $config['delDir'];
$filename = date('Ymd') . '.jpg';
$cachedFile = $cacheDir . $filename;

if (is_file($cachedFile) && filesize($cachedFile) > 0) {
    outputJpeg($cachedFile);
}

$cachedFiles = glob($cacheDir . '*.jpg');
if (is_array($cachedFiles) && $cachedFiles) {
    usort($cachedFiles, function ($left, $right) {
        return filemtime($right) <=> filemtime($left);
    });
    if (is_file($cachedFiles[0]) && filesize($cachedFiles[0]) > 0) {
        outputJpeg($cachedFiles[0]);
    }
}

if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
    outputFallback();
}

$apiUrl = 'https://www.bing.com/HPImageArchive.aspx?format=js&idx=0&n=1';
$apiResponse = fetchRemote($apiUrl, 10);
$apiData = $apiResponse !== false ? json_decode($apiResponse, true) : null;
$imagePath = is_array($apiData) && !empty($apiData['images'][0]['url'])
    ? (string) $apiData['images'][0]['url']
    : '';

if ($imagePath === '') {
    outputFallback();
}

$imageUrl = strpos($imagePath, 'https://') === 0
    ? $imagePath
    : 'https://www.bing.com' . (strpos($imagePath, '/') === 0 ? $imagePath : '/' . $imagePath);
$imageData = fetchRemote($imageUrl, 20);

if ($imageData === false || $imageData === '') {
    outputFallback();
}

$tempFile = $cachedFile . '.tmp.' . getmypid();
if (@file_put_contents($tempFile, $imageData, LOCK_EX) === false || !@rename($tempFile, $cachedFile)) {
    @unlink($tempFile);
    outputFallback();
}

outputJpeg($cachedFile);

function fetchRemote($url, $timeout)
{
    $curl = curl_init($url);
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'EasyImage/1.0',
    ));
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    return $body !== false && $status >= 200 && $status < 300 ? $body : false;
}

function outputJpeg($file)
{
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=3600');
    readfile($file);
    exit;
}

function outputFallback()
{
    header('Content-Type: image/svg+xml; charset=UTF-8');
    header('Cache-Control: public, max-age=300');
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 900"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#dff7ee"/><stop offset="1" stop-color="#b7d9ef"/></linearGradient></defs><rect width="1600" height="900" fill="url(#g)"/><circle cx="1260" cy="180" r="190" fill="#ffffff" fill-opacity=".32"/><circle cx="320" cy="760" r="260" fill="#ffffff" fill-opacity=".2"/></svg>';
    exit;
}
