<?php

namespace Ssdk\Client;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class Client
 *
 * {@inheritdoc}
 *
 * 微服务客户端
 *
 * @package Ssdk\Client
 */
class Client
{
    /**
     * 最大尝试次数
     */
    const MAX_TRY_TIMES = 3;

    /**
     * @var 接口网关
     */
    private $gateway;

    /**
     * @var 请求尝试次数,不超过3次
     */
    private $try_times;

    /**
     * @var HttpClient HTTP客户端
     */
    private $http_client;

    /**
     * @var 服务标识
     */
    private $service_name;

    /**
     * @var 负载均衡器
     */
    private $load_balance;

    /**
     * @var 配置
     */
    private $config;

    //日志组件
    private $logger;

    /**
     * Client constructor.
     * @param $config
     */
    public function __construct($config)
    {
        //设置配置
        $this->setConfig($config);

        //加载配置
        $this->loadConfig();

        //生成HTTP客户端
        $this->setHttpClient(app(HttpClient::class));

        //设置负载均衡器
        if ($this->isClientLoadBalance()) {
            $this->setLoadBalance(app(LoadBalance::class));
        }

        //设置日志组件
        $this->setLogger(app(Log::class));
    }

    /**
     * 获取统一网关
     *
     * @return mixed
     */
    public function getGateway()
    {
        return $this->gateway;
    }

    /**
     * 设置统一网关
     *
     * @param mixed $gateway
     * @return $this
     */
    public function setGateway($gateway)
    {
        $this->gateway = $gateway;
        return $this;
    }

    /**
     * 获取请求尝试次数
     *
     * @return 请求尝试次数
     */
    public function getTryTimes()
    {
        return $this->try_times;
    }

    /**
     * 设置请求尝试次数
     *
     * @param 请求尝试次数 $try_times
     * @return $this
     */
    public function setTryTimes($try_times)
    {
        $this->try_times = $try_times;
        return $this;
    }

    /**
     * 获取HTTP客户端
     *
     * @return HttpClient
     */
    public function getHttpClient()
    {
        return $this->http_client;
    }

    /**
     * 设置HTTP客户端
     *
     * @param HttpClient $http_client
     * @return $this
     */
    public function setHttpClient($http_client)
    {
        $this->http_client = $http_client;
        return $this;
    }

    /**
     * 获取服务标识
     *
     * @return 服务标识
     */
    public function getServiceName()
    {
        return $this->service_name;
    }

    /**
     * 设置服务标识
     *
     * @param 服务标识 $service_name
     * @return $this;
     */
    public function setServiceName($service_name)
    {
        $this->service_name = $service_name;
        return $this;
    }

    /**
     * 获取负载均衡器
     *
     * @return 负载均衡器
     */
    public function getLoadBalance()
    {
        if (!$this->load_balance) {
            $this->setLoadBalance(app(LoadBalance::class));
        }

        return $this->load_balance;
    }

    /**
     * 设置负载均衡器
     *
     * @param 负载均衡器 $load_balance
     * @return $this
     */
    public function setLoadBalance($load_balance)
    {
        $this->load_balance = $load_balance;
        return $this;
    }

    /**
     * 获取配置
     *
     * @return 配置
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * 设置配置
     *
     * @param 配置 $config
     * @return $this
     */
    public function setConfig($config)
    {
        $this->config = $config;
        return $this;
    }

    /**
     * 获取日志组件
     *
     * @return mixed
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * 设置日志组件
     *
     * @param mixed $logger
     * @return $this
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * 加载配置
     */
    private function loadConfig()
    {
        $config = $this->getConfig();
        $this->setGateway($config['gateway']);
        $this->setTryTimes($config['try_times'] > self::MAX_TRY_TIMES ?
            self::MAX_TRY_TIMES : $config['try_times']);
    }

    /**
     * 发起请求
     *
     * @param $method http请求方式
     * @param $entrance 接口入口地址
     * @param array $options 请求参数
     * @return string
     */
    public function performRequest($method, $entrance, array $options)
    {
        $response_body = '';

        if ($this->isClientLoadBalance()) {
            $this->getLoadBalance()->setServiceName($this->getServiceName());
        }

        $except_nodes = [];

        $api_url = '';

        //发起HTTP请求，失败或者异常重试
        for ($i = 0; $i < $this->getTryTimes(); ++$i) {
            try {
                /**
                 * 如果开启客户端负载均衡，则调用load balance获取接口地址
                 * 如果没有开启客户端负载均衡，使用统一网关地址
                 */
                if ($this->isClientLoadBalance()) {
                    $gateway = $this->getLoadBalance()->getTarget($except_nodes);
                } else {
                    $gateway = $this->getGateway();
                }
                $gateway = $this->formatGateway($gateway);
                $entrance = $this->formatEntrance($entrance);
                $api_url = $gateway . $entrance;
                //记录请求日志
                $this->getLogger()->write([
                    'action' => 'request',
                    'method' => $method,
                    'url' => $api_url,
                    'options' => $options,
                ]);
                $res = $this->getHttpClient()->request($method, $api_url, $options);
                $http_status_code = $res->getStatusCode();
                $response_body = $res->getBody()->getContents();
                //记录响应日志
                $this->getLogger()->write([
                    'action' => 'response',
                    'method' => $method,
                    'url' => $api_url,
                    'options' => $options,
                    'http_status_code' => $http_status_code,
                    'response_body' => $response_body,
                ]);

                //请求成功
                if ($http_status_code == 200) {
                    break;
                }

                //请求失败，如果是客户端负载均衡，排除故障节点
                if ($this->isClientLoadBalance()) {
                    $except_nodes[] = $gateway;
                }
            } catch (GuzzleException $e) {
                //记录HTTP客户端异常日志
                $this->getLogger()->write([
                    'action' => 'http_exception',
                    'url' => $api_url,
                    'options' => $options,
                    'method' => $method,
                    'exception_message' => $e->getMessage(),
                    'exception_trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $response_body;
    }

    /**
     * 是否启用客户端负载均衡
     *
     * @return bool
     */
    private function isClientLoadBalance()
    {
        $config = $this->getConfig();
        return boolval($config['client_load_balance']);
    }

    /**
     * 格式化gateway
     *
     * @param $gateway
     * @return string
     */
    private function formatGateway($gateway)
    {
        return rtrim($gateway, '/');
    }

    /**
     * 格式化entrance
     *
     * @param $entrance
     * @return string
     */
    private function formatEntrance($entrance)
    {
        return '/' . ltrim($entrance);
    }
}
