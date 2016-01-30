<?php declare(strict_types = 1);

namespace App\RabbitMq;

use App\Exception\InvalidStateException;
use Guzzle;
use Kdyby;
use Nette;
use RabbitMq\ManagementApi\Client as ManagementClient;



class RabbitManagement
{

	/**
	 * @var string
	 */
	private $vhost;

	/**
	 * @var ManagementClient
	 */
	private $client;

	/**
	 * @var Kdyby\RabbitMq\Connection
	 */
	private $rabbit;



	public function __construct($vhost, ManagementClient $client, Kdyby\RabbitMq\Connection $rabbit)
	{
		$this->vhost = $vhost;
		$this->client = $client;
		$this->rabbit = $rabbit;
	}



	public function isConsumerRunning($name) : bool
	{
		foreach ($this->getQueueOptions($name) as $queueOptions) {
			if (!$queue = $this->fetchRabbitQueueStatus($queueOptions['name'])) {
				return FALSE;
			}

			if (!isset($queue['consumers']) || $queue['consumers'] == 0) {
				return FALSE;
			}
		}

		return TRUE;
	}



	/**
	 * @param string $name
	 * @return array
	 */
	private function getQueueOptions($name) : array
	{
		/** @var Kdyby\RabbitMq\MultipleConsumer $consumer */
		$consumer = $this->rabbit->getConsumer($name);

		if ($consumer instanceof Kdyby\RabbitMq\MultipleConsumer) {
			return $consumer->getQueues();
		}

		return [$consumer->getQueueOptions()];
	}



	/**
	 * @param string $queueName
	 * @return array|null
	 */
	private function fetchRabbitQueueStatus($queueName)
	{
		try {
			return $this->client->queues()->get($this->vhost, $queueName);

		} catch (Guzzle\Http\Exception\ClientErrorResponseException $e) {
			if ($e->getResponse()->getStatusCode() === Nette\Http\IResponse::S404_NOT_FOUND) {
				throw new InvalidStateException(sprintf('Queue %s not found in vhost %s', $queueName, $this->vhost), 0, $e);
			}

			return NULL;
		}
	}

}
