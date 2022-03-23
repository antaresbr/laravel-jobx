<?php
namespace Antares\Jobx\Http;

use Antares\Http\AbstractHttpErrors;

class JobxHttpErrors extends AbstractHttpErrors
{
    public const FAIL_TO_SCHEDULE = 993001;

    public const MESSAGES = [
        self::FAIL_TO_SCHEDULE => 'job::errors.fail_to_schedule',
    ];
}
