<?php declare(strict_types = 1);

namespace App;

use Kdyby;
use Nette;
use PhpAmqpLib\Message\AMQPMessage;



class EmailQueue implements Kdyby\RabbitMq\IConsumer
{

    /**
     * @var Kdyby\RabbitMq\Connection
     */
    private $rabbit;



    public function __construct(Kdyby\RabbitMq\Connection $rabbit)
    {
        $this->rabbit = $rabbit;
    }



    public function publish($to, $from, $body, $highPriority = FALSE)
    {
        $msg = [
            'to' => $to,
            'from' => $from,
            'body' => $body,
        ];

        $this->rabbit
            ->getProducer('emails')
            ->publish(json_encode($msg), $highPriority ? 'emails.high' : 'emails.low');
    }



    public function process(AMQPMessage $raw) : int
    {
        if (!is_array($msg = json_decode($raw->body, TRUE))) {
            return self::MSG_REJECT;
        }

//        if (rand(0, 100) === 5) {
//            throw new \RuntimeException('DIE');
//        }

        usleep(200);

        // send email
        file_put_contents(__DIR__ . '/../../log/emails.log',
            sprintf("[%s] %s -> %s: %s\n", date('Y-m-d H:i:s'), $msg['from'], $msg['to'], $msg['body']),
            FILE_APPEND);

        return self::MSG_ACK;
    }

}
