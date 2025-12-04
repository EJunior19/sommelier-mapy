<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class SommelierLog
{
    public static function info(string $msg): void
    {
        Log::channel('sommelier')->info($msg);
    }

    public static function error(string $msg): void
    {
        Log::channel('sommelier')->error($msg);
    }
}
