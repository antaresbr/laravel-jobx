<?php
namespace Antares\Jobx\Http;

use Antares\Http\AbstractHttpErrors;

class JobxHttpErrors extends AbstractHttpErrors
{
    public const FAIL_TO_SCHEDULE = 993001;
    public const NO_USER_IN_REQUEST = 993002;
    public const USER_IS_NOT_JOB_OWNER = 993003;
    public const JOB_NOT_FOUND = 993004;
    public const JOB_IDS_PARAMETER_NOT_PROVIDED = 993005;
    public const JOB_IDS_PARAMETER_INVALID = 993006;
    
    public const MESSAGES = [
        self::FAIL_TO_SCHEDULE => 'jobx::errors.fail_to_schedule',
        self::NO_USER_IN_REQUEST => 'jobx::errors.no_user_in_request',
        self::USER_IS_NOT_JOB_OWNER => 'jobx::errors.user_is_not_job_owner',
        self::JOB_NOT_FOUND => 'jobx::errors.job_not_found',
        self::JOB_IDS_PARAMETER_NOT_PROVIDED => 'jobx::errors.job_ids_parameter_not_provided',
        self::JOB_IDS_PARAMETER_INVALID => 'jobx::errors.job_ids_parameter_invalid',
    ];
}
