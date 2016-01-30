<?php declare(strict_types = 1);

namespace App;

use App;
use App\Exception\InvalidStateException;
use Kdyby;
use Kdyby\RabbitMq\Connection;
use Kdyby\RabbitMq\DI\RabbitMqExtension;
use Nette;
use PhpAmqpLib\Message\AMQPMessage;
use Tester;



class RabbitProducerMock extends Kdyby\RabbitMq\Producer
{

	/**
	 * @var array[]
	 */
	public $messages = [];

	/**
	 * @var Nette\DI\Container
	 */
	private $serviceLocator;



	public function __construct(Connection $conn, $consumerTag = NULL, Nette\DI\Container $serviceLocator = NULL)
	{
		parent::__construct($conn, $consumerTag);
		$this->serviceLocator = $serviceLocator;
	}



	public function publish($msgBody, $routingKey = '', $additionalProperties = [])
	{
		$this->messages[$routingKey][] = $msgBody;
	}



	/**
	 * @internal do not use unless you're absolutely sure what you're doing
	 * @return array
	 */
	public function processMessages() : array
	{
		$results = [];
		while (count($this->messages) !== 0) {
			$results[] = $this->processMessage();
		}

		return $results;
	}



	/**
	 * @internal do not use unless you're absolutely sure what you're doing
	 * @return bool|mixed
	 */
	public function processMessage()
	{
		if (!$next = $this->nextMessage()) {
			return FALSE;
		}

		list($routingKey, $msgBody) = $next;

		if (!$consumer = $this->findConsumer($routingKey)) {
			throw new InvalidStateException(sprintf('No consumer for exchange "%s" with routing key "%s" found.', $this->exchangeOptions['name'], $routingKey));
		}

		$callbackRefl = new Nette\Reflection\Property($consumer, 'callback');
		$callbackRefl->setAccessible(TRUE);
		$callback = $callbackRefl->getValue($consumer);

		return call_user_func($callback, new AMQPMessage($msgBody, []));
	}



	/**
	 * @param string $routingKey
	 * @return Kdyby\RabbitMq\Consumer
	 */
	private function findConsumer($routingKey)
	{
		foreach ($this->serviceLocator->findByTag(RabbitMqExtension::TAG_CONSUMER) as $consumerService => $_) {
			/** @var Kdyby\RabbitMq\Consumer $consumer */
			$consumer = $this->serviceLocator->getService($consumerService);

			if ($consumer instanceof Kdyby\RabbitMq\MultipleConsumer) {
				continue; // todo: not yet implemented
			}

			if ($consumer->exchangeOptions['name'] !== $this->exchangeOptions['name']) {
				continue; // nope
			}

			if (empty($routingKey)) {
				return $consumer;
			}

			continue; // todo: not yet implemented
		}

		return NULL;
	}



	private function nextMessage()
	{
		foreach ($this->messages as $routingKey => $messages) {
			foreach ($messages as $i => $message) {
				unset($this->messages[$routingKey][$i]);
				if (empty($this->messages[$routingKey])) {
					unset($this->messages[$routingKey]);
				}

				return [$routingKey, $message];
			}
		}

		return NULL;
	}

}
