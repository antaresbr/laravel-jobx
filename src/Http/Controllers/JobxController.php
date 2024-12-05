<?php

namespace Antares\Jobx\Http\Controllers;

use Antares\Http\JsonResponse;
use Antares\Jobx\Http\JobxHttpErrors;
use Antares\Jobx\Models\JobxModel;
use Antares\Socket\Socket;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class JobxController extends Controller
{
    protected function getUser(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return JsonResponse::error(JobxHttpErrors::error(JobxHttpErrors::NO_USER_IN_REQUEST));
        }
        return $user;
    }


    protected function getSocket(Request $request, $job_id)
    {
        $user = $this->getUser($request);
        if (is_a($user, Response::class)) {
            return $user;
        }

        $socket = Socket::createFromId($job_id);
        if (!$socket) {
            return JsonResponse::error(
                JobxHttpErrors::error(JobxHttpErrors::JOB_NOT_FOUND),
                null,
                ['job_id' => $job_id]
            )->setStatusCode(404);
        }
        if ($socket->get('user') != $user->id) {
            return JsonResponse::error(
                JobxHttpErrors::error(JobxHttpErrors::USER_IS_NOT_JOB_OWNER),
                null,
                ['request_user' => $user->id, 'job_user' => $socket->get('user')]
            );
        }
        return $socket;
    }


    public function getFromDB(Request $request)
    {
        $user = $this->getUser($request);
        if (is_a($user, Response::class)) {
            return $user;
        }
        
        $dbjobs = JobxModel::where('user_id', $user->id)->orderBy('id')->get();

        $data = [];
        foreach ($dbjobs as $dbjob) {
            $age = Carbon::now()->diffInDays($dbjob->created_at);
            if ($age > config('jobx.ttl')) {
                $dbjob->delete();
                continue;
            }

            $socket = Socket::createFromId($dbjob->job_id);
            $data[] = $socket->data();

            $dbjob->syncWithSocket($socket);
        }

        return JsonResponse::successful($data);
    }


    public function getItem(Request $request, $job_id)
    {
        $socket = $this->getSocket($request, $job_id);
        if (is_a($socket, Response::class)) {
            return $socket;
        }
        return JsonResponse::successful($socket->data());
    }


    public function getList(Request $request)
    {
        $user = $this->getUser($request);
        if (is_a($user, Response::class)) {
            return $user;
        }

        $job_ids = $request->input('job_ids', null);
        if (is_null($job_ids)) {
            return JsonResponse::error(JobxHttpErrors::error(JobxHttpErrors::JOB_IDS_PARAMETER_NOT_PROVIDED));
        }
        if (!is_array($job_ids)) {
            return JsonResponse::error(JobxHttpErrors::error(JobxHttpErrors::JOB_IDS_PARAMETER_INVALID), null, ['job_ids' => $job_ids]);
        }

        $ids = [];
        $data = [];
        foreach($job_ids as $job_id) {
            if (in_array($job_id, $ids)) {
                continue;
            }
            $ids[] = $job_id;
            $socket = Socket::createFromId($job_id);
            if (!is_null($socket) and $socket->get('user') == $user->id) {
                array_push($data, $socket->data());
            }
        }

        return JsonResponse::successful($data);
    }


    public function see(Request $request, $job_id)
    {
        $socket = $this->getSocket($request, $job_id);
        if (is_a($socket, Response::class)) {
            return $socket;
        }

        $socket->set('seen', true);
        JobxModel::fromSocket($socket);

        return JsonResponse::successful($socket->data());
    }


    public function cancel(Request $request, $job_id)
    {
        $socket = $this->getSocket($request, $job_id);
        if (is_a($socket, Response::class)) {
            return $socket;
        }

        $socket->cancel(true);
        JobxModel::fromSocket($socket);

        return JsonResponse::successful($socket->data());
    }
}
