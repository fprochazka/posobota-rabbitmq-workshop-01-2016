<?php

/** @var \Nette\DI\Container $container */
$container = require_once __DIR__ . '/../app/bootstrap.php';

$emailQueue = $container->getByType(\App\EmailQueue::class);

\Tracy\Debugger::timer('messages');

for ($i = 0; $i < 1000000; $i++) {
    $emailQueue->publish(
        sprintf('spam+%s@example.com', $i),
        'filip@prochazka.su',
        "AHOOOJ"
    );
}

print_r(['time' => \Tracy\Debugger::timer('messages')]);
