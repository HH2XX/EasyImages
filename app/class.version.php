<?php

/**
 * 原作者: Icret
 * 维护分支: HH2XX/EasyImages
 * 获取GitHub最新版本号
 * 基于 EasyImage2.0 创建时间: 2021-5-10 14:17:25
 * 修改时间: 2023-02-10 11:23:30
 * @param int 864000 10天的unix时间,既每隔十天获取一次版本
 */

class getVersion
{
    const CACHE_TTL = 864000;

    private $url;
    private $dir;
    private $filePath;

    public function __construct($url)
    {
        $this->url = $url;
        $this->dir = __DIR__ . '/../admin/logs/version/';
        $this->filePath = $this->dir . 'easyimage-version.json';
    }

    public function readJson($name = 'tag_name')
    {
        $version = $this->readCache();

        if ($version === null || $this->isCacheExpired()) {
            $freshVersion = $this->downJson();
            if ($freshVersion !== null) {
                $version = $freshVersion;
            }
        }

        if (!is_array($version) || !array_key_exists($name, $version)) {
            return null;
        } else {
            return $version[$name];
        }
    }

    public function downJson()
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $response = $this->geturl($this->url);
        if ($response === null) {
            return null;
        }

        $version = json_decode($response, true);
        if (!is_array($version)) {
            return null;
        }

        if (!is_dir($this->dir) && !mkdir($this->dir, 0755, true)) {
            return null;
        }

        if (file_put_contents($this->filePath, json_encode($version), LOCK_EX) === false) {
            return null;
        }

        return $version;
    }

    public function geturl($url)
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $headerArray = array('Accept: application/vnd.github+json');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        curl_setopt($ch, CURLOPT_USERAGENT, 'EasyImage-Version-Check');
        $output = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $statusCode === 200 && is_string($output) ? $output : null;
    }

    private function readCache()
    {
        if (!is_file($this->filePath) || filesize($this->filePath) === 0) {
            return null;
        }

        $content = file_get_contents($this->filePath);
        $version = json_decode($content, true);

        return is_array($version) ? $version : null;
    }

    private function isCacheExpired()
    {
        return !is_file($this->filePath) || (time() - filemtime($this->filePath)) > self::CACHE_TTL;
    }
}

/////////// TEST /////////
/*
$url = "https://api.github.com/repos/HH2XX/EasyImages/releases/latest";
$test = new getVersion($url);
echo $test->readJson();
*/
