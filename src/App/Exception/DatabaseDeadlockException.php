<?php declare(strict_types = 1);

namespace App\Exception;

use Kdyby;
use Nette;



class DatabaseDeadlockException extends \RuntimeException
{

    public static function fromDriverException(\Exception $e) : DatabaseDeadlockException
    {
    }

}
