<?php

namespace App\Logging;

use Monolog\Formatter\LineFormatter;

class CustomLineFormatter extends LineFormatter
{
    public function __construct()
    {
        parent::__construct(
            "[%datetime%] %level_name%: %message% %context% %extra%\n",
            "Y-m-d H:i:s"
        );
    }
}
