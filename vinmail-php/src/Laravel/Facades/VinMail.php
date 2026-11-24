<?php

declare(strict_types=1);

namespace VinWorld\VinMail\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use VinWorld\VinMail\EmailsResource;

/**
 * @method static EmailsResource emails()
 */
class VinMail extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'vinmail';
    }
}
