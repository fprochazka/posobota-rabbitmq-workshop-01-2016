<?php declare(strict_types = 1);

namespace App\RabbitMq;

use App\Exception\InvalidArgumentException;
use App\Exception\InvalidStateException;
use App\Stub\Identified;
use Doctrine\Common\Util\ClassUtils;
use Kdyby;
use Nette;
use Nette\Utils\Json;
use PhpAmqpLib\Message\AMQPMessage;



abstract class BufferedQueue extends Nette\Object implements Kdyby\RabbitMq\IConsumer, Kdyby\Events\Subscriber
{

	/**
	 * @var array
	 */
	protected $dirtyEntities = [];

	/**
	 * @var Kdyby\RabbitMq\Connection
	 */
	protected $rabbitmq;

	/**
	 * @var Kdyby\Doctrine\EntityManager
	 */
	protected $em;

	/**
	 * @var string
	 */
	protected $exchangeName;

	/**
	 * @var bool
	 */
	private $autoFlush = FALSE;



	public function __construct($exchangeName, Kdyby\RabbitMq\Connection $rabbitmq, Kdyby\Doctrine\EntityManager $em)
	{
		$this->rabbitmq = $rabbitmq;
		$this->em = $em;
		$this->exchangeName = $exchangeName;
	}



	public function setAutoFlush(bool $flush = TRUE)
	{
		$this->autoFlush = $flush;
	}



	public function getSubscribedEvents()
	{
		return ['Nette\Application\Application::onShutdown' => 'flushSync'];
	}



	protected function markDirty(Identified $entity, array $data = [])
	{
		if (empty($entity->getId())) {
			throw new InvalidArgumentException('Entity has no identifier');
		}

		$class = ClassUtils::getRealClass(get_class($entity));
		$this->dirtyEntities[$class][$entity->getId()] = $data;

		if ($this->autoFlush) {
			$this->flushSync();
		}
	}



	protected function prepare($type, $id, array $data) : string
	{
		return Json::encode(['type' => $type, 'id' => $id] + $data);
	}



	public function flushSync()
	{
		if ($this->exchangeName === NULL) {
			throw new InvalidStateException('Exchange name is not defined');
		}

		if (!array_filter($this->dirtyEntities)) {
			return;
		}

		$producer = $this->rabbitmq->getProducer($this->exchangeName);

		foreach ($this->dirtyEntities as $type => $entities) {
			foreach ($entities as $id => $data) {
				$producer->publish($this->prepare($type, $id, $data));
			}
		}

		$this->dirtyEntities = [];
	}



	abstract public function process(AMQPMessage $message) : int;

}
