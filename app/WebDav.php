<?php

/**
 * Minimal WebDAV client for upload storage channels.
 *
 * Supports the operations EasyImage needs: create folders, upload, delete,
 * move, and a lightweight connection check. It intentionally avoids composer
 * dependencies so shared hosting can use it as long as the curl extension is on.
 */
class WebDavClient
{
    private $baseUrl;
    private $username;
    private $password;
    private $rootPath;
    private $timeout;
    private $verifySsl;

    public function __construct($baseUrl, $username = '', $password = '', $rootPath = '', $timeout = 30, $verifySsl = true)
    {
        $this->baseUrl = rtrim(trim((string) $baseUrl), '/');
        $this->username = (string) $username;
        $this->password = (string) $password;
        $this->rootPath = trim(str_replace('\\', '/', (string) $rootPath), '/');
        $this->timeout = max(1, (int) $timeout);
        $this->verifySsl = (bool) $verifySsl;

        if ($this->baseUrl === '') {
            throw new InvalidArgumentException('WebDAV address is empty.');
        }
    }

    public function putFile($remotePath, $localFile)
    {
        if (!is_file($localFile)) {
            throw new InvalidArgumentException('Local file does not exist: ' . $localFile);
        }

        $this->mkcolRecursive(dirname($remotePath));

        $fp = fopen($localFile, 'rb');
        if (!$fp) {
            throw new RuntimeException('Can not open local file: ' . $localFile);
        }

        $mime = function_exists('mime_content_type') ? mime_content_type($localFile) : 'application/octet-stream';
        $headers = array(
            'Content-Type: ' . ($mime ?: 'application/octet-stream'),
            'Content-Length: ' . filesize($localFile),
        );

        try {
            $status = $this->request('PUT', $remotePath, $headers, $fp, filesize($localFile));
        } finally {
            fclose($fp);
        }

        if (!in_array($status, array(200, 201, 204), true)) {
            throw new RuntimeException('WebDAV upload failed, HTTP status: ' . $status);
        }

        return true;
    }

    public function delete($remotePath)
    {
        $status = $this->request('DELETE', $remotePath);
        if (!in_array($status, array(200, 202, 204, 404), true)) {
            throw new RuntimeException('WebDAV delete failed, HTTP status: ' . $status);
        }

        return true;
    }

    public function getFile($remotePath)
    {
        $responseBody = '';
        $status = $this->request('GET', $remotePath, array(), null, null, $responseBody, true);
        if ($status === 404) {
            return false;
        }
        if ($status !== 200) {
            throw new RuntimeException('WebDAV read failed, HTTP status: ' . $status);
        }

        return $responseBody;
    }

    public function move($fromPath, $toPath)
    {
        $this->mkcolRecursive(dirname($toPath));

        $status = $this->request('MOVE', $fromPath, array(
            'Destination: ' . $this->buildUrl($toPath),
            'Overwrite: T',
        ));

        if (!in_array($status, array(200, 201, 204, 404), true)) {
            throw new RuntimeException('WebDAV move failed, HTTP status: ' . $status);
        }

        return true;
    }

    public function listPath($remotePath = '/')
    {
        $responseBody = '';
        $status = $this->request('PROPFIND', $remotePath, array('Depth: 1'), null, null, $responseBody);
        if (!in_array($status, array(200, 207), true)) {
            throw new RuntimeException('WebDAV list failed, HTTP status: ' . $status);
        }

        return $responseBody;
    }

    public function test()
    {
        if ($this->rootPath !== '') {
            $this->mkcolRecursive('/');
        }

        $status = $this->request('PROPFIND', '/', array('Depth: 0'));
        if (!in_array($status, array(200, 207), true)) {
            throw new RuntimeException('WebDAV connection failed, HTTP status: ' . $status);
        }

        return true;
    }

    private function mkcolRecursive($remoteDir)
    {
        $targetDir = trim($this->rootPath . '/' . trim(str_replace('\\', '/', (string) $remoteDir), '/'), '/');
        if ($targetDir === '' || $targetDir === '.') {
            return true;
        }

        $current = '';
        foreach (explode('/', $targetDir) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                continue;
            }

            $current .= '/' . $part;
            $status = $this->requestAbsolute('MKCOL', $current);
            if (!in_array($status, array(200, 201, 204, 405), true)) {
                throw new RuntimeException('WebDAV create directory failed, HTTP status: ' . $status);
            }
        }

        return true;
    }

    private function request($method, $remotePath, array $headers = array(), $bodyHandle = null, $bodySize = null, &$responseBody = null, $followRedirects = false)
    {
        return $this->requestUrl($method, $this->buildUrl($remotePath), $headers, $bodyHandle, $bodySize, $responseBody, $followRedirects);
    }

    private function requestAbsolute($method, $path, array $headers = array(), $bodyHandle = null, $bodySize = null, &$responseBody = null, $followRedirects = false)
    {
        return $this->requestUrl($method, $this->buildUrlFromPath($path), $headers, $bodyHandle, $bodySize, $responseBody, $followRedirects);
    }

    private function requestUrl($method, $url, array $headers = array(), $bodyHandle = null, $bodySize = null, &$responseBody = null, $followRedirects = false)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        if (defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }

        if ($followRedirects) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        }

        if ($this->username !== '' || $this->password !== '') {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        }

        $headers = array_merge(array('X-Requested-With: XMLHttpRequest', 'Expect:'), $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (is_resource($bodyHandle)) {
            curl_setopt($ch, CURLOPT_UPLOAD, true);
            curl_setopt($ch, CURLOPT_INFILE, $bodyHandle);
            if ($bodySize !== null) {
                curl_setopt($ch, CURLOPT_INFILESIZE, $bodySize);
            }
        }

        $result = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result === false) {
            throw new RuntimeException('WebDAV request failed: ' . $error);
        }

        if ($responseBody !== null) {
            $responseBody = $result;
        }

        return $status;
    }

    private function buildUrl($remotePath)
    {
        return $this->buildUrlFromPath($this->rootPath . '/' . ltrim(str_replace('\\', '/', (string) $remotePath), '/'));
    }

    private function buildUrlFromPath($path)
    {
        $parts = array();
        $path = trim(str_replace('\\', '/', (string) $path), '/');

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = rawurlencode($part);
        }

        return $this->baseUrl . ($parts ? '/' . implode('/', $parts) : '');
    }
}

