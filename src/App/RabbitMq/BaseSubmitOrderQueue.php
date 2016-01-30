<?php declare(strict_types = 1);

namespace App\RabbitMq;

use App\Stub\Order;
use App\Stub\UserContext;
use Kdyby;
use Kdyby\Doctrine\EntityManager;
use Kdyby\Monolog\Logger;
use Kdyby\RabbitMq\Connection;
use Kdyby\RabbitMq\TerminateException;
use Nette;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use PhpAmqpLib\Message\AMQPMessage;
use Tracy;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
abstract class BaseSubmitOrderQueue implements Kdyby\RabbitMq\IConsumer
{

    const DELAY_ON_RESTART = 1;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Connection
     */
    private $rabbit;

    /**
     * @var UserContext
     */
    private $user;



    public function injectPrimary(UserContext $user, EntityManager $em, Connection $rabbit, Logger $logger)
    {
        $this->user = $user;
        $this->em = $em;
        $this->rabbit = $rabbit;
        $this->logger = $logger->channel($this->getQueue());
    }



    public function append(Order $order, $retryCounter = 0, array $options = [])
    {
        $message = [
            'orderId' => $order->getId(),
            'retryCounter' => $retryCounter,
            'options' => $options,
        ];

        $this->rabbit->getProducer('submitOrder')
            ->publish(Json::encode($message), $this->getQueue());
    }



    public function process(AMQPMessage $message) : int
    {
        try {
            if (!$request = Json::decode($message->body, Json::FORCE_ARRAY)) {
                return self::MSG_REJECT;
            }

        } catch (JsonException $e) {
            $this->logger->addError($e->getMessage());

            return self::MSG_REJECT;
        }

        $orderId = $request['orderId'];

        try {
            $conn = $this->em->getConnection();
            if ($conn->ping() === false) {
                $conn->close();
                $conn->connect();
            }

            /** @var Order $order */
            $orders = $this->em->getRepository(Order::class);
            if (!$order = $orders->find($orderId)) {
                $this->logger->addWarning(sprintf('Order %s not found in db', $orderId));

                return self::MSG_REJECT;
            }

            if (!$order->getUser()) {
                $this->logger->addWarning(sprintf('Order %s has no user', $orderId));
                return self::MSG_REJECT;
            }

            $this->user->passwordLessLogin($order->getUser()->getId());

            return $this->processOrder($order, $request['options']);

        } catch (\App\Exception\DatabaseDeadlockException $e) {
            return $this->restartJob($request, $e);

        } catch (\Doctrine\DBAL\Exception\DriverException $e) {
            if ($deadlock = \App\Exception\DatabaseDeadlockException::fromDriverException($e)) {
                $e = $deadlock;
                $message = null;

            } else {
                $this->logger->addError($e->getMessage(), ['exception' => $e]);
                $message = $e->getPrevious() instanceof \Doctrine\DBAL\Driver\PDOException ? $e->getPrevious()->getMessage() : $e->getMessage();
            }

            return $this->restartJob($request, $e, $message);

        } catch (\Exception $e) {
            $this->logger->addError($this->getQueue(), ['exception' => $e]);

            return $this->restartJob($request, $e);

        } finally {
            $this->user->logout(true);
            $this->em->clear();
        }
    }



    abstract protected function getQueue() : string;



    abstract protected function processOrder(Order $order, array $options) : int;



    /**
     * @throws TerminateException
     */
    protected function restartJob(array $request, \Exception $exception = null, $message = null) : int
    {
        $this->logger->addError(
            sprintf(
                'job requeue(%s) for order %d because of %s: %s',
                $request['retryCounter'],
                $request['orderId'],
                get_class($exception),
                $message ?: $exception->getMessage()
            )
        );

        $this->append(
            $this->em->getReference(Order::class, $request['orderId']),
            $request['retryCounter'] + 1,
            $request['options']
        );

        if (!$this->em->isOpen()) {
            sleep(self::DELAY_ON_RESTART);
            throw TerminateException::withResponse(self::MSG_REJECT);
        }

        return self::MSG_REJECT;
    }

}
