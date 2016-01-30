<?php

namespace App\RabbitMq;

use Kdyby;
use Kdyby\RabbitMq\Consumer;
use Nette;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;



class RabbitConsumerListener extends Nette\Object implements Kdyby\Events\Subscriber
{

    /**
     * @var OutputInterface
     */
    private $output;



    public function __construct()
    {
        $this->output = new ConsoleOutput();
    }



    public function getSubscribedEvents()
    {
        return [
            'Kdyby\RabbitMq\Consumer::onConsume' => 'msgOpened',
            'Kdyby\RabbitMq\Consumer::onReject' => 'msgRejected',
        ];
    }



    public function msgOpened(Consumer $consumer, AMQPMessage $msg)
    {
        $routingKey = isset($msg->delivery_info['routing_key']) ? $msg->delivery_info['routing_key'] : null;
        $exchange = isset($msg->delivery_info['exchange']) ? $msg->delivery_info['exchange'] : null;
        $this->output->writeln(sprintf('<info>%s</info>, exchange: <info>%s</info>, routing_key: <info>%s</info>', $msg->body, $exchange, $routingKey));
    }



    public function msgRejected(Consumer $consumer, AMQPMessage $msg)
    {
        $this->output->writeln('<error>The message was not properly handled</error>');
    }

}
