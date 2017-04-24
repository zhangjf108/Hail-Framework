<?php

namespace Hail\Filesystem\Adapter;

use LogicException;
use Hail\Filesystem\Client\Dropbox as Client;
use Hail\Filesystem\Client\BadRequestException;
use Hail\Filesystem\Adapter\Polyfill\NotSupportingVisibilityTrait;

class Dropbox extends AbstractAdapter
{
    use NotSupportingVisibilityTrait;

    /** @var Client */
    protected $client;

    public function __construct(array $config)
    {
        if (!isset($config['accessToken'])) {
            throw new \InvalidArgumentException('Dropbox access token not defined');
        }

        $this->client = new Client($config['accessToken']);

        $this->setPathPrefix($config['prefix'] ?? '');
    }

    /**
     * {@inheritdoc}
     */
    public function write($path, $contents, array $config)
    {
        return $this->upload($path, $contents, 'add');
    }

    /**
     * {@inheritdoc}
     */
    public function writeStream($path, $resource, array $config)
    {
        return $this->upload($path, $resource, 'add');
    }

    /**
     * {@inheritdoc}
     */
    public function update($path, $contents, array $config)
    {
        return $this->upload($path, $contents, 'overwrite');
    }

    /**
     * {@inheritdoc}
     */
    public function updateStream($path, $resource, array $config)
    {
        return $this->upload($path, $resource, 'overwrite');
    }

    /**
     * {@inheritdoc}
     */
    public function rename($path, $newPath): bool
    {
        $path = $this->applyPathPrefix($path);
        $newPath = $this->applyPathPrefix($newPath);

        try {
            $this->client->move($path, $newPath);
        } catch (BadRequestException $e) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function copy($path, $newpath): bool
    {
        $path = $this->applyPathPrefix($path);
        $newpath = $this->applyPathPrefix($newpath);

        try {
            $this->client->copy($path, $newpath);
        } catch (BadRequestException $e) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($path): bool
    {
        $location = $this->applyPathPrefix($path);

        try {
            $this->client->delete($location);
        } catch (BadRequestException $e) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDir($dirname): bool
    {
        return $this->delete($dirname);
    }

    /**
     * {@inheritdoc}
     */
    public function createDir($dirname, array $config)
    {
        $path = $this->applyPathPrefix($dirname);

        try {
            $object = $this->client->createFolder($path);
        } catch (BadRequestException $e) {
            return false;
        }

        return $this->normalizeResponse($object);
    }

    /**
     * {@inheritdoc}
     */
    public function has($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function read($path)
    {
        if (! $object = $this->readStream($path)) {
            return false;
        }

        $object['contents'] = stream_get_contents($object['stream']);
        fclose($object['stream']);
        unset($object['stream']);

        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {
        $path = $this->applyPathPrefix($path);

        try {
            $stream = $this->client->download($path);
        } catch (BadRequestException $e) {
            return false;
        }

        return compact('stream');
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($directory = '', $recursive = false): array
    {
        $location = $this->applyPathPrefix($directory);

        $result = $this->client->listFolder($location, $recursive);

        if (! count($result['entries'])) {
            return [];
        }

        return array_map([$this, 'normalizeResponse'], $result['entries']);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path)
    {
        $path = $this->applyPathPrefix($path);

        try {
            $object = $this->client->getMetadata($path);
        } catch (BadRequestException $e) {
            return false;
        }

        return $this->normalizeResponse($object);
    }

    /**
     * {@inheritdoc}
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getMimetype($path)
    {
        throw new LogicException("The Dropbox API v2 does not support mimetypes. Given path: `{$path}`.");
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    public function getTemporaryLink(string $path): string
    {
        return $this->client->getTemporaryLink($path);
    }

    public function getThumbnail(string $path, string $format = 'jpeg', string $size = 'w64h64')
    {
        return $this->client->getThumbnail($path, $format, $size);
    }

    /**
     * {@inheritdoc}
     */
    public function applyPathPrefix($path): string
    {
        $path = parent::applyPathPrefix($path);

        return '/'.trim($path, '/');
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @param string $path
     * @param resource|string $contents
     * @param string $mode
     *
     * @return array|false file metadata
     */
    protected function upload(string $path, $contents, string $mode)
    {
        $path = $this->applyPathPrefix($path);

        try {
            $object = $this->client->upload($path, $contents, $mode);
        } catch (BadRequestException $e) {
            return false;
        }

        return $this->normalizeResponse($object);
    }

    protected function normalizeResponse(array $response): array
    {
        $normalizedPath = ltrim($this->removePathPrefix($response['path_display']), '/');

        $normalizedResponse = ['path' => $normalizedPath];

        if (isset($response['server_modified'])) {
            $normalizedResponse['timestamp'] = strtotime($response['server_modified']);
        }

        if (isset($response['size'])) {
            $normalizedResponse['bytes'] = $response['size'];
        }

        $type = ($response['.tag'] === 'folder' ? 'dir' : 'file');
        $normalizedResponse['type'] = $type;

        return $normalizedResponse;
    }
}