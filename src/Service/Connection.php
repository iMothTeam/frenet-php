<?php

declare(strict_types = 1);

namespace Frenet\Service;

use Psr\Http\Message\ResponseInterface;
use Frenet\Framework\Http\Response as FrameworkResponse;

/**
 * Class Connection
 *
 * @package Frenet\Service
 */
class Connection implements ConnectionInterface
{
    /**
     * @var \Frenet\Service\ClientFactory
     */
    private $clientFactory;
    
    /**
     * @var Response\SuccessFactory
     */
    private $responseSuccessFactory;
    
    /**
     * @var Response\ExceptionFactory
     */
    private $responseExceptionFactory;
    
    /**
     * @var ResultFactory
     */
    private $resultFactory;
    
    /**
     * @var \Frenet\ConfigPool
     */
    private $configPool;
    
    /**
     * @var \Frenet\Event\EventDispatcherInterface
     */
    private $eventDispatcher;
    
    /**
     * Connection constructor.
     *
     * @param \Frenet\ConfigPool                     $configPool
     * @param \Frenet\Event\EventDispatcherInterface $eventDispatcher
     * @param ClientFactory                          $clientFactory
     * @param Response\SuccessFactory                $responseSuccessFactory
     * @param Response\ExceptionFactory              $responseExceptionFactory
     * @param ResultFactory                          $resultFactory
     */
    public function __construct(
        \Frenet\ConfigPool $configPool,
        \Frenet\Event\EventDispatcherInterface $eventDispatcher,
        ClientFactory $clientFactory,
        Response\SuccessFactory $responseSuccessFactory,
        Response\ExceptionFactory $responseExceptionFactory,
        ResultFactory $resultFactory
    ) {
        $this->configPool = $configPool;
        $this->eventDispatcher = $eventDispatcher;
        $this->clientFactory = $clientFactory;
        $this->responseSuccessFactory = $responseSuccessFactory;
        $this->responseExceptionFactory = $responseExceptionFactory;
        $this->resultFactory = $resultFactory;
    }
    
    /**
     * {@inheritdoc}
     */
    public function post($resourcePath, array $data = [], array $config = [])
    {
        return $this->request(self::METHOD_POST, $resourcePath, $data, $config);
    }
    
    /**
     * {@inheritdoc}
     */
    public function get($resourcePath, array $data = [], array $config = [])
    {
        return $this->request(self::METHOD_GET, $resourcePath, $data, $config);
    }
    
    /**
     * {@inheritdoc}
     */
    public function request($method, $resourcePath, array $data = [], array $config = [])
    {
        $bodyType = 'json';
        
        if (isset($config['type']) && $config['type']) {
            $bodyType = $config['type'];
        }
        
        /** @var string $uri */
        $uri = $this->buildUri($resourcePath, $config);
        
        $options = [
            $bodyType => $data
        ];
        
        return $this->makeRequest($method, $uri, $options);
    }
    
    /**
     * @param string $method
     * @param string $uri
     * @param array  $options
     *
     * @return FrameworkResponse\ResponseInterface
     */
    private function makeRequest($method, $uri, array $options = [])
    {
        $options['headers'] = [
            'token' => $this->configPool->credentials()->getToken(),
        ];
        
        $eventData = [
            'method'  => $method,
            'uri'     => $uri,
            'options' => $options,
        ];
        
        try {
            $this->eventDispatcher->dispatch('connection_request_before', $eventData);
            
            /** @var ResponseInterface $response */
            $response = $this->client()->request($method, $uri, $options);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            $eventData['exception'] = $e;
            $this->eventDispatcher->dispatch('connection_request_exception', $eventData);
            return $this->respondException($e);
        } catch (\Exception $e) {
            $eventData['exception'] = $e;
            $this->eventDispatcher->dispatch('connection_request_exception', $eventData);
            return $this->respondException($e);
        }
    
        $eventData['response'] = $response;
        $this->eventDispatcher->dispatch('connection_request_exception', $eventData);
        
        return $this->respondSuccess($response);
    }
    
    /**
     * @param ResponseInterface $response
     *
     * @return FrameworkResponse\ResponseInterface
     */
    private function respondSuccess(ResponseInterface $response)
    {
        $response = $this->responseSuccessFactory->create([
            'response' => $response
        ]);
        
        return $this->sendResult($response);
    }
    
    /**
     * @param \Exception $exception
     *
     * @return FrameworkResponse\ResponseInterface
     */
    private function respondException(\Exception $exception)
    {
        $response = $this->responseExceptionFactory->create([
            'exception' => $exception
        ]);
        
        return $this->sendResult($response);
    }
    
    /**
     * @param FrameworkResponse\ResponseInterface $response
     *
     * @return FrameworkResponse\ResponseInterface
     */
    private function sendResult(FrameworkResponse\ResponseInterface $response)
    {
        return $response;
    }
    
    /**
     * @return \GuzzleHttp\ClientInterface
     */
    private function client()
    {
        return $this->clientFactory->create();
    }
    
    /**
     * @param string $resourcePath
     * @param array  $config
     *
     * @return string
     */
    private function buildUri($resourcePath, array $config = [])
    {
        $query = (array) (isset($config['query']) ? $config['query'] : null);
        $url = vsprintf('%s://%s/%s', [
            $this->configPool->service()->getProtocol(),
            $this->configPool->service()->getHost(),
            $resourcePath,
        ]);
        
        if (!empty($query)) {
            // $url = $url;
        }
        
        return $url;
    }
}
