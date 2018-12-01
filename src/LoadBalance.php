<?php

namespace Ssdk\Client;

use Illuminate\Support\Facades\Redis;

/**
 * Class LoadBalance
 *
 * {@inheritdoc}
 *
 * 负载均衡器
 *
 * @package Ssdk\Client
 */
class LoadBalance
{
    /**
     * @var 服务标识
     */
    private $service_name;

    /**
     * @var int 最大轮询次数
     */
    private $max_polling = 10000000;

    /**
     * @var int 默认初始节点id
     */
    private $default_target_id = 1;

    /**
     * @var \Redis
     */
    private $redis;

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
     * @return $this
     */
    public function setServiceName($service_name)
    {
        $this->service_name = $service_name;
        return $this;
    }

    /**
     * 获取最大轮询次数
     *
     * @return int
     */
    public function getMaxPolling()
    {
        return $this->max_polling;
    }

    /**
     * 设置最大轮询次数
     *
     * @param int $max_polling
     * @return $this
     */
    public function setMaxPolling($max_polling)
    {
        $this->max_polling = $max_polling;
        return $this;
    }

    /**
     * 获取默认初始节点id
     *
     * @return int
     */
    public function getDefaultTargetId()
    {
        return $this->default_target_id;
    }

    /**
     * 设置默认初始节点id
     *
     * @param int $default_target_id
     * @return $this
     */
    public function setDefaultTargetId($default_target_id)
    {
        $this->default_target_id = $default_target_id;
        return $this;
    }

    /**
     * 获取redis客户端
     *
     * @return \Redis
     */
    public function getRedis()
    {
        return $this->redis;
    }

    /**
     * 设置redis客户端
     *
     * @param \Redis $redis
     * @return $this
     */
    public function setRedis($redis)
    {
        $this->redis = $redis;
        return $this;
    }

    /**
     * LoadBalance constructor.
     * @param $config
     */
    public function __construct($config)
    {
        //加载配置
        $this->loadConfig($config);
    }

    /**
     * 加载配置
     *
     * @param $config 配置
     */
    private function loadConfig($config)
    {
        $this->setMaxPolling($config['max_polling']);
        $this->setDefaultTargetId($config['default_target_id']);
        $this->setRedis(Redis::connection($config['redis']['connection']));
    }

    /**
     * 获取节点地址列表
     *
     * @param array $except_nodes 尝试过的故障节点
     * @return array
     */
    public function getNodes(array $except_nodes = [])
    {
        $nodes = [];

        $redis = $this->getRedis();

        $service_names_key = $this->getServiceNamesKey();
        if ($redis->hExists($service_names_key, $this->getServiceName())) { //判断服务是否存在
            $service_list_key = $this->getServiceListKey();
            if ($redis->exists($service_list_key)) {
                $nodes = $redis->hKeys($service_list_key);
                //排除尝试过的故障节点
                $nodes = array_diff($nodes, $except_nodes);
            }
        }

        return $nodes;
    }

    /**
     * 获取目标节点地址
     *
     * @param array $except_nodes 尝试过的故障节点
     * @return mixed|string
     */
    public function getTarget(array $except_nodes = [])
    {
        $nodes = $this->getNodes($except_nodes);
        $num_nodes = count($nodes);
        if ($num_nodes <= 0) {
            return '';
        }

        $polling = $this->getNextPolling($num_nodes);

        $target_id = $polling % $num_nodes;

        return $nodes[$target_id];
    }

    /**
     * 获取下一个轮询数
     *
     * @param $num_nodes
     * @return int
     */
    private function getNextPolling($num_nodes)
    {
        $redis = $this->getRedis();
        $service_polling_key = $this->getServicePollingKey();
        $polling = $redis->hIncrBy($service_polling_key, $this->getServiceName(), 1);
        $max_polling = $this->_getMaxPolling($num_nodes);
        //超过最大轮询，重置为1
        if (($polling == $max_polling + 1) || ($polling > (2 * $max_polling))) {
            $polling = $this->getDefaultTargetId();
            $redis->hSet($service_polling_key, $this->getServiceName(), $polling);
        }

        return $polling;
    }

    /**
     * 获取最大轮询数
     *
     * @param $num_nodes
     * @return int
     */
    private function _getMaxPolling($num_nodes)
    {
        $max_polling = $this->getMaxPolling();
        return $max_polling >= $num_nodes ? $max_polling : $num_nodes;
    }

    /**
     * 获取服务名称redis key
     *
     * @return string
     */
    private function getServiceNamesKey()
    {
        return 'service:names';
    }

    /**
     * 获取轮询次数redis key
     *
     * @return string
     */
    private function getServicePollingKey()
    {
        return  'service:polling';
    }

    /**
     * 获取节点地址列表redis key
     *
     * @return string
     */
    private function getServiceListKey()
    {
        return 'service:list:' . $this->getServiceName();
    }
}
