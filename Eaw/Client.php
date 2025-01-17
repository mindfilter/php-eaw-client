<?php

namespace Eaw;

use Eaw\Traits\AuthenticatesClient;
use Eaw\Traits\BuildsHttpRequestData;
use Eaw\Traits\MakesCrudRequests;
use Eaw\Traits\IsSingleton;
use Exception;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class Client
{
    use IsSingleton;
    use BuildsHttpRequestData;
    use MakesCrudRequests;
    use AuthenticatesClient;

    /**
     * @var Guzzle https://docs.guzzlephp.org/en/6.5/
     */
    protected $guzzle;

    /**
     * @var CurlMultiHandler
     */
    protected $handler;

    /**
     * @var string Base API URL.
     */
    protected $baseUrl = 'https://api.easyatwork.com';

    /**
     * @var string[] Headers to include in all requests.
     */
    protected $headers = [
        'User-Agent' => 'php-eaw-client',
    ];

    /**
     * @var bool[] Client options.
     */
    protected $options = [
        'catch_rate_limit' => true,
        'follow_url_hint' => true,
    ];

    /** @var LoggerInterface */
    protected $logger;

    protected function __construct()
    {
        $this->handler = new CurlMultiHandler([
            'select_timeout' => 0.1,
        ]);

        $this->guzzle = new Guzzle([
            'handler' => HandlerStack::create($this->handler),
        ]);

        $this->logger = Logger::getInstance()->getLogger('client');
    }

    /**
     * @param array $options
     * @return array
     */
    public function setOptions(array $options): array
    {
        return $this->options = $options + $this->options;
    }

    /**
     * @return LoggerInterface
     */
    protected function logger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Tick the cURL event loop.
     */
    public function tick()
    {
        $this->handler->tick();
    }

    /**
     * Tick until all requests have completed.
     */
    public function execute()
    {
        $this->handler->execute();
    }

    /**
     * @param ResponseInterface $response
     * @return false|int
     */
    protected function isRateLimited(ResponseInterface $response)
    {
        if (!$this->options['catch_rate_limit']) {
            return false;
        }

        if ($response->getStatusCode() != 429) {
            return false;
        }

        return $response->getHeader('Retry-After')[0] ?? 10;
    }

    /**
     * @param ResponseInterface $response
     * @return false|string
     */
    protected function followUrlHint(ResponseInterface $response)
    {
        if (!$this->options['follow_url_hint']) {
            return false;
        }

        if (!$response->hasHeader('X-API-URL')) {
            return false;
        }

        $apiUrl = $response->getHeader('X-API-URL')[0];

        if ($this->baseUrl == $apiUrl) {
            return false;
        }

        return $this->baseUrl = $apiUrl;
    }

    /**
     * @param string $method
     * @param string $path
     * @param array|null $parameters
     * @param array|null $data
     * @param array|null $files
     * @param array $options
     * @return array
     */
    protected function request(string $method = 'GET', string $path = '/', array $parameters = null, array $data = null, array $files = null, array $options = []): array
    {
        $options['synchronous'] = true;

        return $this->requestAsync($method, $path, $parameters, $data, $files, $options)->wait(true);
    }

    /**
     * @param string $method
     * @param string $path
     * @param array|null $parameters
     * @param array|null $data
     * @param array|null $files
     * @param array $options
     * @return PromiseInterface<array>
     */
    protected function requestAsync(string $method = 'GET', string $path = '/', array $parameters = null, array $data = null, array $files = null, array $options = []): PromiseInterface
    {
        return $this->guzzle->requestAsync(
                $method,
                $this->buildRequestUrl($path, $parameters),
                $this->buildRequestOptions($data, $files) + $options
            )
            ->then(function (ResponseInterface $response) {
                if (false !== $newUrl = $this->followUrlHint($response)) {
                    $this->logger()->debug('Switching API URL to "' . $newUrl . '"...');
                }

                $encoded = (string) $response->getBody();

                if ($encoded === '') {
                    $encoded = json_encode($encoded);
                }

                $decoded = json_decode($encoded, true);

                if ($decoded === null) {
                    throw new Exception(json_last_error_msg());
                }

                if ($decoded === '') {
                    $decoded = [];
                }

                return $decoded;
            })
            ->otherwise(function ($exception) use ($method, $path, $parameters, $data, $files) {
                if ($exception instanceof ClientException) {
                    if (false !== $retryAfter = $this->isRateLimited($exception->getResponse())) {
                        if ($retryAfter) {
                            $this->logger()->notice('Rate limit reached. Retrying in ' . $retryAfter . ' seconds...');
                        }

                        return $this->requestAsync($method, $path, $parameters, $data, $files, ['delay' => $retryAfter * 1000]);
                    }
                }

                throw $exception;
            });
    }

    /**
     * @param string $path
     * @param array $parameters
     * @return Paginator
     */
    public function readPaginated(string $path, array $parameters = []): Paginator
    {
        return new Paginator(
            $this,
            $path,
            $parameters
        );
    }

    /**
     * @param string $path
     * @return QueryBuilder
     */
    public function query(string $path): QueryBuilder
    {
        return new QueryBuilder($this, $path);
    }
}
