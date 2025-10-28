<?php

declare(strict_types=1);

namespace Hypervel\Tests\Horizon\Feature\Exceptions;

use Exception;
use Hypervel\Foundation\Exceptions\Contracts\ShouldntReport;

class DontReportException extends Exception implements ShouldntReport
{
}
