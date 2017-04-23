<?php

namespace Hail\Http\Middleware;

use Hail\Http\Middleware\Util\NegotiatorTrait;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\ServerMiddleware\DelegateInterface;

class ContentType implements MiddlewareInterface
{
    use NegotiatorTrait;

    /**
     * @var string Default format
     */
    private $default = 'html';

    /**
     * @var array Available formats with the mime types
     */
    private $formats;

    /**
     * @var bool Include X-Content-Type-Options: nosniff
     */
    private $nosniff = true;

    /**
     * Define de available formats.
     *
     * @param array|null $formats
     */
    public function __construct(array $formats = null)
    {
        $this->formats = $formats ?: [
            //text
            'html' => [
                'extension' => ['html', 'htm', 'php'],
                'mime-type' => ['text/html', 'application/xhtml+xml'],
                'charset' => true,
            ],
            'txt' => [
                'extension' => ['txt'],
                'mime-type' => ['text/plain'],
                'charset' => true,
            ],
            'css' => [
                'extension' => ['css'],
                'mime-type' => ['text/css'],
                'charset' => true,
            ],
            'json' => [
                'extension' => ['json'],
                'mime-type' => ['application/json', 'text/json', 'application/x-json'],
                'charset' => true,
            ],
            'jsonp' => [
                'extension' => ['jsonp'],
                'mime-type' => ['text/javascript', 'application/javascript', 'application/x-javascript'],
                'charset' => true,
            ],
            'js' => [
                'extension' => ['js'],
                'mime-type' => ['text/javascript', 'application/javascript', 'application/x-javascript'],
                'charset' => true,
            ],

            //xml
            'rdf' => [
                'extension' => ['rdf'],
                'mime-type' => ['application/rdf+xml'],
                'charset' => true,
            ],
            'rss' => [
                'extension' => ['rss'],
                'mime-type' => ['application/rss+xml'],
                'charset' => true,
            ],
            'atom' => [
                'extension' => ['atom'],
                'mime-type' => ['application/atom+xml'],
                'charset' => true,
            ],
            'xml' => [
                'extension' => ['xml'],
                'mime-type' => ['text/xml', 'application/xml', 'application/x-xml'],
                'charset' => true,
            ],

            //images
            'bmp' => [
                'extension' => ['bmp'],
                'mime-type' => ['image/bmp'],
            ],
            'gif' => [
                'extension' => ['gif'],
                'mime-type' => ['image/gif'],
            ],
            'png' => [
                'extension' => ['png'],
                'mime-type' => ['image/png', 'image/x-png'],
            ],
            'jpg' => [
                'extension' => ['jpg', 'jpeg', 'jpe'],
                'mime-type' => ['image/jpeg', 'image/jpg'],
            ],
            'svg' => [
                'extension' => ['svg', 'svgz'],
                'mime-type' => ['image/svg+xml'],
            ],
            'psd' => [
                'extension' => ['psd'],
                'mime-type' => ['image/vnd.adobe.photoshop'],
            ],
            'eps' => [
                'extension' => ['ai', 'eps', 'ps'],
                'mime-type' => ['application/postscript'],
            ],
            'ico' => [
                'extension' => ['ico'],
                'mime-type' => ['image/x-icon', 'image/vnd.microsoft.icon'],
            ],

            //audio/video
            'mov' => [
                'extension' => ['mov', 'qt'],
                'mime-type' => ['video/quicktime'],
            ],
            'mp3' => [
                'extension' => ['mp3'],
                'mime-type' => ['audio/mpeg'],
            ],
            'mp4' => [
                'extension' => ['mp4'],
                'mime-type' => ['video/mp4'],
            ],
            'ogg' => [
                'extension' => ['ogg'],
                'mime-type' => ['audio/ogg'],
            ],
            'ogv' => [
                'extension' => ['ogv'],
                'mime-type' => ['video/ogg'],
            ],
            'webm' => [
                'extension' => ['webm'],
                'mime-type' => ['video/webm'],
            ],
            'webp' => [
                'extension' => ['webp'],
                'mime-type' => ['image/webp'],
            ],

            //fonts
            'eot' => [
                'extension' => ['eot'],
                'mime-type' => ['application/vnd.ms-fontobject'],
            ],
            'otf' => [
                'extension' => ['otf'],
                'mime-type' => ['font/opentype', 'application/x-font-opentype'],
            ],
            'ttf' => [
                'extension' => ['ttf'],
                'mime-type' => ['font/ttf', 'application/font-ttf', 'application/x-font-ttf'],
            ],
            'woff' => [
                'extension' => ['woff'],
                'mime-type' => ['font/woff', 'application/font-woff', 'application/x-font-woff'],
            ],
            'woff2' => [
                'extension' => ['woff2'],
                'mime-type' => ['font/woff2', 'application/font-woff2', 'application/x-font-woff2'],
            ],

            //other formats
            'pdf' => [
                'extension' => ['pdf'],
                'mime-type' => ['application/pdf', 'application/x-download'],
            ],
            'zip' => [
                'extension' => ['zip'],
                'mime-type' => ['application/zip', 'application/x-zip', 'application/x-zip-compressed'],
            ],
            'rar' => [
                'extension' => ['rar'],
                'mime-type' => ['application/rar', 'application/x-rar', 'application/x-rar-compressed'],
            ],
            'exe' => [
                'extension' => ['exe'],
                'mime-type' => ['application/x-msdownload'],
            ],
            'msi' => [
                'extension' => ['msi'],
                'mime-type' => ['application/x-msdownload'],
            ],
            'cab' => [
                'extension' => ['cab'],
                'mime-type' => ['application/vnd.ms-cab-compressed'],
            ],
            'doc' => [
                'extension' => ['doc'],
                'mime-type' => ['application/msword'],
            ],
            'rtf' => [
                'extension' => ['rtf'],
                'mime-type' => ['application/rtf'],
            ],
            'xls' => [
                'extension' => ['xls'],
                'mime-type' => ['application/vnd.ms-excel'],
            ],
            'ppt' => [
                'extension' => ['ppt'],
                'mime-type' => ['application/vnd.ms-powerpoint'],
            ],
            'odt' => [
                'extension' => ['odt'],
                'mime-type' => ['application/vnd.oasis.opendocument.text'],
            ],
            'ods' => [
                'extension' => ['ods'],
                'mime-type' => ['application/vnd.oasis.opendocument.spreadsheet'],
            ],
        ];
    }

    /**
     * Set the default format.
     *
     * @param string $format
     *
     * @return self
     */
    public function defaultFormat($format)
    {
        $this->default = $format;

        return $this;
    }

    /**
     * Configure the nosniff option.
     *
     * @param bool $nosniff
     *
     * @return self
     */
    public function nosniff($nosniff = true)
    {
        $this->nosniff = $nosniff;

        return $this;
    }

    /**
     * Process a server request and return a response.
     *
     * @param ServerRequestInterface $request
     * @param DelegateInterface      $delegate
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $format = $this->detectFromExtension($request) ?: $this->detectFromHeader($request) ?: $this->default;
        $contentType = $this->formats[$format]['mime-type'][0];

        $request = $request
            ->withHeader('Accept', $contentType)
            ->withHeader('Accept-Charset', 'UTF-8');

        $response = $delegate->process($request);

        if (!$response->hasHeader('Content-Type')) {
            $needCharset = !empty($this->formats[$format]['charset']);

            if ($needCharset) {
                $contentType .= '; charset=UTF-8';
            }

            $response = $response->withHeader('Content-Type', $contentType);
        }

        if ($this->nosniff && !$response->hasHeader('X-Content-Type-Options')) {
            $response = $response->withHeader('X-Content-Type-Options', 'nosniff');
        }

        return $response;
    }

    /**
     * Returns the format using the file extension.
     *
     * @return null|string
     */
    private function detectFromExtension(ServerRequestInterface $request)
    {
        $extension = strtolower(pathinfo($request->getUri()->getPath(), PATHINFO_EXTENSION));

        if (empty($extension)) {
            return null;
        }

        foreach ($this->formats as $format => $data) {
            if (in_array($extension, $data['extension'], true)) {
                return $format;
            }
        }
    }

    /**
     * Returns the format using the Accept header.
     *
     * @return null|string
     */
    private function detectFromHeader(ServerRequestInterface $request)
    {
        $mimes = array_column($this->formats, 'mime-type');
        $headers = array_merge(...$mimes);

        $accept = $request->getHeaderLine('Accept');
        $mime = $this->getBest($accept, $headers);

        if ($mime === null) {
            foreach ($this->formats as $format => $data) {
                if (in_array($mime, $data['mime-type'], true)) {
                    return $format;
                }
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function pareseAccept($accept): array
    {
        $accept = self::normalPareseAccept($accept);

        $type = $accept['type'];
        if ($type === '*') {
            $type = '*/*';
        }

        $parts = explode('/', $type);

        if (count($parts) !== 2 || !$parts[0] || !$parts[1]) {
            throw new \RuntimeException('Accept header parse failed: ' . $type);
        }

        $accept['type'] = $type;
        $accept['basePart'] = $parts[0];
        $accept['subPart'] = $parts[1];

        return $accept;
    }

    /**
     * {@inheritdoc}
     */
    protected function match(array $accept, array $priority, $index)
    {
        $ab = $accept['basePart'];
        $pb = $priority['basePart'];

        $as = $accept['subPart'];
        $ps = $priority['subPart'];

        $intersection = array_intersect_assoc($accept['parameters'], $priority['parameters']);

        $baseEqual = !strcasecmp($ab, $pb);
        $subEqual = !strcasecmp($as, $ps);

        if (($ab === '*' || $baseEqual) && ($as === '*' || $subEqual) && count($intersection) === count($accept['parameters'])) {
            $score = 100 * $baseEqual + 10 * $subEqual + count($intersection);

            return [
                'quality' => $accept['quality'] * $priority['quality'],
                'score' => $score,
                'index' => $index,
            ];
        }

        return null;
    }
}