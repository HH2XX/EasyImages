<?php

/**
 * Minimal S3-compatible storage client.
 *
 * Works with Cloudflare R2, Backblaze B2, MinIO and most S3-compatible object
 * storage providers without requiring Composer packages.
 */
class S3StorageClient
{
    private $endpoint;
    private $bucket;
    private $accessKey;
    private $secretKey;
    private $region;
    private $rootPath;
    private $timeout;
    private $verifySsl;
    private $pathStyle;

    public function __construct($endpoint, $bucket, $accessKey, $secretKey, $region = 'auto', $rootPath = '', $timeout = 30, $verifySsl = true, $pathStyle = true)
    {
        $this->endpoint = rtrim(trim((string) $endpoint), '/');
        $this->bucket = trim((string) $bucket);
        $this->accessKey = trim((string) $accessKey);
        $this->secretKey = (string) $secretKey;
        $this->region = trim((string) $region) ?: 'auto';
        $this->rootPath = trim(str_replace('\\', '/', (string) $rootPath), '/');
        $this->timeout = max(1, (int) $timeout);
        $this->verifySsl = (bool) $verifySsl;
        $this->pathStyle = (bool) $pathStyle;

        if ($this->endpoint === '' || $this->bucket === '' || $this->accessKey === '' || $this->secretKey === '') {
            throw new InvalidArgumentException('S3 config is incomplete.');
        }
    }

    public function putFile($remotePath, $localFile)
    {
        if (!is_file($localFile)) {
            throw new InvalidArgumentException('Local file does not exist: ' . $localFile);
        }

        $body = file_get_contents($localFile);
        if ($body === false) {
            throw new RuntimeException('Can not read local file: ' . $localFile);
        }

        $mime = function_exists('mime_content_type') ? mime_content_type($localFile) : 'application/octet-stream';
        return $this->putObject($remotePath, $body, $mime ?: 'application/octet-stream');
    }

    public function putObject($remotePath, $body, $contentType = 'application/octet-stream')
    {
        $status = $this->request('PUT', $remotePath, (string) $body, array(
            'content-type' => $contentType ?: 'application/octet-stream',
        ));

        if (!in_array($status, array(200, 201, 204), true)) {
            throw new RuntimeException('S3 upload failed, HTTP status: ' . $status);
        }

        return true;
    }

    public function delete($remotePath)
    {
        $status = $this->request('DELETE', $remotePath);
        if (!in_array($status, array(200, 202, 204, 404), true)) {
            throw new RuntimeException('S3 delete failed, HTTP status: ' . $status);
        }

        return true;
    }

    public function getObject($remotePath)
    {
        $responseBody = '';
        $status = $this->request('GET', $remotePath, '', array(), $responseBody);
        if ($status === 404) {
            return false;
        }
        if ($status !== 200) {
            throw new RuntimeException('S3 read failed, HTTP status: ' . $status);
        }

        return $responseBody;
    }

    public function test()
    {
        $testKey = '.easyimage-storage-test-' . date('YmdHis') . '-' . mt_rand(1000, 9999) . '.txt';
        $this->putObject($testKey, 'EasyImage S3 storage test ' . date('c'), 'text/plain; charset=utf-8');
        $this->delete($testKey);

        return true;
    }

    private function request($method, $remotePath, $body = '', array $extraHeaders = array(), &$responseBody = null)
    {
        $url = $this->buildUrl($remotePath);
        $urlParts = parse_url($url);
        $host = isset($urlParts['host']) ? $urlParts['host'] : '';
        if (isset($urlParts['port'])) {
            $host .= ':' . $urlParts['port'];
        }

        $payloadHash = hash('sha256', (string) $body);
        $amzDate = gmdate('Ymd\THis\Z');
        $shortDate = gmdate('Ymd');
        $headers = array_merge(array(
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $amzDate,
        ), $this->normalizeHeaders($extraHeaders));

        ksort($headers);

        $canonicalHeaders = '';
        $signedHeaders = array();
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= $name . ':' . trim((string) $value) . "\n";
            $signedHeaders[] = $name;
        }
        $signedHeadersString = implode(';', $signedHeaders);
        $canonicalUri = isset($urlParts['path']) ? $urlParts['path'] : '/';
        $canonicalQueryString = isset($urlParts['query']) ? $urlParts['query'] : '';

        $canonicalRequest = strtoupper($method) . "\n"
            . $canonicalUri . "\n"
            . $canonicalQueryString . "\n"
            . $canonicalHeaders . "\n"
            . $signedHeadersString . "\n"
            . $payloadHash;

        $credentialScope = $shortDate . '/' . $this->region . '/s3/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n"
            . $amzDate . "\n"
            . $credentialScope . "\n"
            . hash('sha256', $canonicalRequest);

        $signingKey = $this->signatureKey($this->secretKey, $shortDate, $this->region, 's3');
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        $headers['authorization'] = 'AWS4-HMAC-SHA256 Credential=' . $this->accessKey . '/' . $credentialScope . ', SignedHeaders=' . $signedHeadersString . ', Signature=' . $signature;

        $curlHeaders = array();
        foreach ($headers as $name => $value) {
            $curlHeaders[] = $name . ': ' . $value;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        if (defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }

        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $result = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result === false) {
            throw new RuntimeException('S3 request failed: ' . $error);
        }

        if ($responseBody !== null) {
            $responseBody = $result;
        }

        return $status;
    }

    private function normalizeHeaders(array $headers)
    {
        $normalized = array();
        foreach ($headers as $name => $value) {
            $normalized[strtolower(trim((string) $name))] = trim((string) $value);
        }
        return $normalized;
    }

    private function signatureKey($key, $dateStamp, $regionName, $serviceName)
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $key, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    private function buildUrl($remotePath)
    {
        $key = $this->buildObjectKey($remotePath);
        $encodedKey = $this->encodePath($key);

        if ($this->pathStyle) {
            return $this->endpoint . '/' . rawurlencode($this->bucket) . ($encodedKey !== '' ? '/' . $encodedKey : '');
        }

        $parts = parse_url($this->endpoint);
        $scheme = isset($parts['scheme']) ? $parts['scheme'] : 'https';
        $host = isset($parts['host']) ? $parts['host'] : preg_replace('#^https?://#i', '', $this->endpoint);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $basePath = isset($parts['path']) ? trim($parts['path'], '/') : '';
        $prefix = $basePath !== '' ? '/' . $this->encodePath($basePath) : '';

        return $scheme . '://' . $this->bucket . '.' . $host . $port . $prefix . ($encodedKey !== '' ? '/' . $encodedKey : '');
    }

    private function buildObjectKey($remotePath)
    {
        $path = trim(str_replace('\\', '/', (string) $remotePath), '/');
        if ($this->rootPath !== '') {
            $path = trim($this->rootPath . '/' . $path, '/');
        }

        $safeParts = array();
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($safeParts);
                continue;
            }
            $safeParts[] = $part;
        }

        return implode('/', $safeParts);
    }

    private function encodePath($path)
    {
        $parts = array();
        foreach (explode('/', trim((string) $path, '/')) as $part) {
            if ($part !== '') {
                $parts[] = rawurlencode($part);
            }
        }

        return implode('/', $parts);
    }
}
