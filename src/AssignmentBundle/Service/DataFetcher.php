<?php
/**
 * User:    213
 * Date:    15.03.2016
 * Time:    16.42
 */

namespace AssignmentBundle\Service;

use AssignmentBundle\Interfaces\Formatter;
use AssignmentBundle\Interfaces\ItemSorter;
use AssignmentBundle\Model\Item;
use GuzzleHttp\Client;
use Tedivm\StashBundle\Service\CacheService;

class DataFetcher
{
    /** @var string */
    protected $sourceUrl;
    /** @var Formatter */
    protected $formatter;
    /** @var ItemSorter */
    protected $itemSorter;
    /** @var bool|int */
    protected $limit = false;
    /** @var int */
    protected $ttl;
    /** @var CacheService */
    protected $cache;

    /**
     * DataFetcher constructor.
     *
     * @param $sourceUrl
     * @param Formatter $formatter
     * @param ItemSorter $itemSorter
     * @param CacheService $cache
     */
    public function __construct($sourceUrl, Formatter $formatter, ItemSorter $itemSorter, CacheService $cache)
    {
        $this->sourceUrl = $sourceUrl;
        $this->formatter = $formatter;
        $this->itemSorter = $itemSorter;
        $this->cache = $cache;
    }

    /**
     * Invalidates and updates cache.
     *
     * @throws \Exception
     */
    public function refreshCache()
    {
        $key = md5($this->sourceUrl);
        $this->cache->deleteItem($key);

        $data = $this->getHttpResponse();
        $item = $this->cache->getItem($key);
        $item->set($data);
        $item->setTTL($this->ttl);
        $item->save();
    }

    /**
     * Fetches, formats and sorts data from the configured source.
     *
     * @return array|Item[]
     */
    public function getFormattedData()
    {
        $data = $this->fetchData();

        $items = $this->formatter->format($data);
        $items = $this->itemSorter->sort($items);
        if ($this->limit) {
            $items = array_slice($items, 0, $this->limit);
        }

        return $items;
    }

    /**
     * Retrives data from cache. Fallbacks to live-sources when cache expires.
     *
     * @return mixed|null|string
     *
     * @throws \Exception
     */
    protected function fetchData()
    {
        $key = md5($this->sourceUrl);
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return $item->get();
        }

        $data = $this->getHttpResponse();

        $item->set($data);
        $item->setTTL($this->ttl);
        $item->save();

        return $data;
    }

    /**
     * Guzzles data from the given sourceUrl.
     *
     * @return string
     *
     * @throws \Exception
     */
    protected function getHttpResponse()
    {
        $client = new Client();
        $response = $client->request('GET', $this->sourceUrl);
        $data = $response->getBody()->getContents();
        if (!$data) {
            throw new \Exception('Got empty response from server.');
        }

        return $data;
    }

    /**
     * @param bool|int $limit
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
    }

    /**
     * @param int $ttl
     */
    public function setTtl($ttl)
    {
        $this->ttl = $ttl;
    }
}

