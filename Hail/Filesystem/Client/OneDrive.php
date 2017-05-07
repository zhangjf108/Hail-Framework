<?php

namespace Hail\Filesystem\Client;

use Hail\Http\Client\Client;
use Hail\Http\Client\Exception\ClientException;
use Hail\Http\Factory;
use Psr\Http\Message\StreamInterface;

/**
 * Class OneDrive
 * $ composer require guzzlehttp/guzzle:^6.1
 *
 * @package Hail\Filesystem\Client
 */
class OneDrive
{
    /**
     * @var string
     */
    private $accessToken;

    /**
     * @var Client
     */
    private $client;

    const BASE_URI = 'https://api.onedrive.com/v1.0/';

    /**
     * @param string $accessToken
     * @param Client $client
     */
    public function __construct($accessToken, Client $client)
    {
        $this->accessToken = $accessToken;
        $this->client = $client;
    }

    /**
     * @param string $path
     *
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @throws OneDriveClientException
     *
     * @link https://dev.onedrive.com/items/download.htm
     */
    public function download($path)
    {
        $url = $this->getDownloadUrlForFile($path);

        return $this->getResponse('GET', $url);
    }

    /**
     * @param string $path
     *
     * @return resource
     *
     * @throws OneDriveClientException
     *
     * @link https://dev.onedrive.com/items/download.htm
     */
    public function downloadStream($path)
    {
        $url = $this->getDownloadUrlForFile($path);
        $input = $this->getStreamForUrl($url);

        $output = fopen('php://temp', 'w+');
        if ($output === false) {
            throw new OneDriveClientException('Error when saving the downloaded file');
        }

        while (!$input->eof()) {
            $writeResult = fwrite($output, $input->read(8192));
            if ($writeResult === false) {
                throw new OneDriveClientException('Error when saving the downloaded file');
            }
        }

        $input->close();

        rewind($output);

        return $output;
    }

    /**
     * @param string $url
     *
     * @return StreamInterface
     */
    public function getStreamForUrl($url)
    {
        $resource = fopen($url, 'r');

        return Factory::stream($resource);
    }

    /**
     * @param string $path
     *
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @link https://dev.onedrive.com/items/get.htm
     */
    public function getMetadata($path)
    {
        $url = self::BASE_URI . $this->getPathUnderRootDrive($path);

        return $this->getResponse('GET', $url);
    }

    /**
     * @param string $path
     * @param string $content
     *
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @link https://dev.onedrive.com/items/upload_put.htm
     */
    public function createFile($path, $content)
    {
        return $this->simpleItemUpload($path, $content, 'fail');
    }

    /**
     * @param string $path
     * @param string $content
     *
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @link https://dev.onedrive.com/items/upload_put.htm
     */
    public function updateFile($path, $content)
    {
        return $this->simpleItemUpload($path, $content, 'replace');
    }

    /**
     * @param string $oldPath
     * @param string $newPath
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function rename($oldPath, $newPath)
    {
        $payload = $this->getNewFileLocationPayload($newPath);

        return $this->updateMetadata($oldPath, $payload);
    }

    /**
     * @param string $oldPath
     * @param string $newPath
     *
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @throws OneDriveClientException
     *
     * @link https://dev.onedrive.com/items/copy.htm
     */
    public function copy($oldPath, $newPath)
    {
        $url = self::BASE_URI . $this->getPathUnderRootDrive($oldPath) . ':/action.copy';

        $payload = $this->getNewFileLocationPayload($newPath);

        $headers = [
            'Content-Type' => 'application/json',
            'Prefer' => 'respond-async',
        ];

        $response = $this->getResponse('POST', $url, json_encode($payload), $headers);
        $asyncStatusLocation = $response->getHeader('Location');

        //check for the status of an async operation
        $completed = false;
        while (!$completed) {
            $statusResponse = $this->getResponse('GET', $asyncStatusLocation[0]);

            $statusCode = $statusResponse->getStatusCode();
            if ($statusCode === 303 || $statusCode === 200) {
                $completed = true;
            } else {
                $statusRaw = $statusResponse->getBody()->getContents();
                $status = json_decode($statusRaw);
                if ($status->status === 'failed') {
                    throw new OneDriveClientException('API error when copying the file');
                }

                //wait some time until the next status check
                sleep(0.5);
            }
        }

        return $this->getMetadata($newPath);
    }

    /**
     * @param string $path
     * @param array  $metadata
     *
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @link https://dev.onedrive.com/items/update.htm
     */
    public function updateMetadata($path, array $metadata)
    {
        $url = self::BASE_URI . $this->getPathUnderRootDrive($path);

        return $this->getResponse('PATCH', $url, json_encode($metadata), ['Content-Type' => 'application/json']);
    }

    /**
     * @param string $path
     *
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @link https://dev.onedrive.com/items/create.htm
     */
    public function createFolder($path)
    {
        $pathinfo = pathinfo($path);

        $parentDir = ($pathinfo['dirname'] !== '.') ? $pathinfo['dirname'] : '';
        $url = self::BASE_URI . $this->getFolderUrl($parentDir) . 'children';

        $folder = [
            'name' => $pathinfo['basename'],
            'folder' => new \StdClass(),
            '@name.conflictBehavior' => 'fail',
        ];

        return $this->getResponse('POST', $url, json_encode($folder), ['Content-Type' => 'application/json']);
    }

    /**
     * @param string $path
     *
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @link https://dev.onedrive.com/items/list.htm
     */
    public function listChildren($path)
    {
        $url = self::BASE_URI . $this->getFolderUrl($path) . 'children';

        return $this->getResponse('GET', $url);
    }

    /**
     * @param string $path
     *
     * @return bool Success
     *
     * @link https://dev.onedrive.com/items/delete.htm
     */
    public function delete($path)
    {
        $url = self::BASE_URI . $this->getPathUnderRootDrive($path);
        $response = $this->getResponse('DELETE', $url);

        return $response->getStatusCode() === 204;
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public function itemExists($path)
    {
        try {
            $this->getMetadata($path);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    /**
     * @param string $path
     * @param string $content
     * @param string $conflictBehavior fail|replace|rename
     *
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @throws OneDriveClientException
     *
     * @link https://dev.onedrive.com/items/upload_put.htm
     */
    private function simpleItemUpload($path, $content, $conflictBehavior)
    {
        if (!in_array($conflictBehavior, ['fail', 'replace', 'rename'], true)) {
            throw new OneDriveClientException('Incorrect conflict behavior parameter value');
        }

        $url = self::BASE_URI . $this->getPathUnderRootDrive($path) . ':/content?@name.conflictBehavior=' . $conflictBehavior;

        return $this->getResponse('PUT', $url, $content);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private function getDownloadUrlForFile($path)
    {
        $metadataRaw = $this->getMetadata($path)->getBody()->getContents();
        $metadata = json_decode($metadataRaw, true);

        return $metadata['@content.downloadUrl'];
    }

    /**
     * Returns the payload for a copy/rename request.
     *
     * @param string $newPath
     *
     * @return array
     */
    private function getNewFileLocationPayload($newPath)
    {
        $pathinfo = pathinfo($newPath);
        $newParentDir = ($pathinfo['dirname'] !== '.') ? $pathinfo['dirname'] : '';
        $newFilename = $pathinfo['basename'];

        $payload = [
            'name' => $newFilename,
            'parentReference' => ['path' => $this->getParentReferenceFolder($newParentDir)],
        ];

        return $payload;
    }

    /**
     * @param string $folder
     *
     * @return string
     */
    private function getParentReferenceFolder($folder)
    {
        if ($folder === '') {
            $parentReference = '/drive/root/';
        } else {
            $parentReference = '/drive/root:/' . $folder;
        }

        return $parentReference;
    }

    /**
     * @param string $folder
     *
     * @return string
     */
    private function getFolderUrl($folder)
    {
        if ($folder === '') {
            $url = 'drive/root/';
        } else {
            $url = $this->getPathUnderRootDrive($folder) . ':/';
        }

        return $url;
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private function getPathUnderRootDrive($path)
    {
        return 'drive/root:/' . $path;
    }

    /**
     * @param string $method
     * @param string $path
     * @param string $body
     * @param array  $headers
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    private function getResponse($method, $path, $body = null, $headers = [])
    {
        $allHeaders = array_merge($this->getAuthorizationHeader(), $headers);
        $uri = $path;

        $request = Factory::request($method, $uri, $allHeaders, $body);

        return $this->client->send($request);
    }

    /**
     * @return array
     *
     * @link https://dev.onedrive.com/auth/msa_oauth.htm
     */
    private function getAuthorizationHeader()
    {
        return ['Authorization' => 'bearer ' . $this->accessToken];
    }
}

