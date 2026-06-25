<?php

namespace Biteslot\Connector\Http\Controllers;

use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Package base controller so the connector's own controllers don't depend on the
 * host application's App\Http\Controllers\Controller (which may not exist or may
 * pull in app-specific traits).
 */
class Controller extends BaseController
{
    use ValidatesRequests;
}
