<?php

namespace Knuckles\Camel\Output;

use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Knuckles\Camel\BaseDTO;
use Knuckles\Camel\Extraction\ResponseCollection;
use Knuckles\Camel\Extraction\ResponseField;
use Knuckles\Scribe\Extracting\Extractor;
use Knuckles\Scribe\Tools\Utils as u;


class OutputEndpointData extends BaseDTO
{
    /**
     * @var array<string>
     */
    public $httpMethods;

    /** @var string */
    public $uri;

    /** @var \Knuckles\Camel\Extraction\Metadata */
    public $metadata;

    /**
     * @var array<string,string>
     */
    public $headers = [];

    /**
     * @var array<string,\Knuckles\Camel\Output\Parameter>
     */
    public $urlParameters = [];

    /**
     * @var array<string,mixed>
     */
    public $cleanUrlParameters = [];

    /**
     * @var array<string,\Knuckles\Camel\Output\Parameter>
     */
    public $queryParameters = [];

    /**
     * @var array<string,mixed>
     */
    public $cleanQueryParameters = [];

    /**
     * @var array<string, \Knuckles\Camel\Output\Parameter>
     */
    public $bodyParameters = [];

    /**
     * @var array<string,mixed>
     */
    public $cleanBodyParameters = [];

    /**
     * @var array
     * @var array<string,\Illuminate\Http\UploadedFile>
     */
    public $fileParameters = [];

    /**
     * @var \Knuckles\Camel\Extraction\ResponseCollection
     */
    public $responses;

    /**
     * @var array<string,\Knuckles\Camel\Extraction\ResponseField>
     */
    public $responseFields = [];

    /**
     * @var array<string, array>
     */
    public $nestedBodyParameters = [];

    /** @var string|null */
    public $boundUri;

    public function __construct(array $parameters = [])
    {
        // spatie/dto currently doesn't auto-cast nested DTOs like that
        $parameters['responses'] = new ResponseCollection($parameters['responses']);
        $parameters['bodyParameters'] = array_map(function ($param) {
            return new Parameter($param);
        }, $parameters['bodyParameters']);
        $parameters['queryParameters'] = array_map(function ($param) {
            return new Parameter($param);
        }, $parameters['queryParameters']);
        $parameters['urlParameters'] = array_map(function ($param) {
            return new Parameter($param);
        }, $parameters['urlParameters']);
        $parameters['responseFields'] = array_map(function ($param) {
            return new ResponseField($param);
        }, $parameters['responseFields']);

        parent::__construct($parameters);

        $this->nestedBodyParameters = Extractor::nestArrayAndObjectFields($this->bodyParameters);

        $this->cleanBodyParameters = Extractor::cleanParams($this->bodyParameters);
        $this->cleanQueryParameters = Extractor::cleanParams($this->queryParameters);
        $this->cleanUrlParameters = Extractor::cleanParams($this->urlParameters);
        $this->boundUri = u::getUrlWithBoundParameters($this->uri, $this->cleanUrlParameters);

        [$files, $regularParameters] = collect($this->cleanBodyParameters)
            ->partition(
                function ($example) {
                    return $example instanceof UploadedFile
                        || (is_array($example) && ($example[0] ?? null) instanceof UploadedFile);
                }
            );
        if (count($files)) {
            $this->headers['Content-Type'] = 'multipart/form-data';
        }
        $this->fileParameters = $files->toArray();
        $this->cleanBodyParameters = $regularParameters->toArray();
    }

    /**
     * @param Route $route
     *
     * @return array<string>
     */
    public static function getMethods(Route $route): array
    {
        $methods = $route->methods();

        // Laravel adds an automatic "HEAD" endpoint for each GET request, so we'll strip that out,
        // but not if there's only one method (means it was intentional)
        if (count($methods) === 1) {
            return $methods;
        }

        return array_diff($methods, ['HEAD']);
    }

    public static function fromExtractedEndpointArray(array $endpoint): OutputEndpointData
    {
        return new self($endpoint);
    }

    public function endpointId(): string
    {
        return $this->httpMethods[0].str_replace(['/', '?', '{', '}', ':'], '-', $this->uri);
    }

    public function hasResponses(): bool
    {
        return count($this->responses) > 0;
    }

    public function hasFiles(): bool
    {
        return count($this->fileParameters) > 0;
    }

    public function isGet(): bool
    {
        return in_array('GET', $this->httpMethods);
    }

    public function hasRequestOptions(): bool
    {
        return !empty($this->headers)
            || !empty($this->cleanQueryParameters)
            || !empty($this->cleanBodyParameters);
    }
}