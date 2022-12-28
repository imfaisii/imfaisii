<?php

namespace DTApi\Http\Controllers;

use App\Traits\Jsonify;
use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    use Jsonify;

    protected $repository;

    public function __construct(BookingRepository $bookingRepository)
    {
        $this->middleware('auth'); // in case not applied in routes file
        $this->repository = $bookingRepository;
    }

    public function index(Request $request): JsonResponse
    {
        /*
        // Refactored role type to ENUMS for consistency ( assuming no database tables are there for roles and permissions )
        // Default case wasn't handled which is now catered
        */

        $response = in_array($request->user()->user_type, [RolesEnum::SUPER_ADMIN, RolesEnum::ADMIN])
            ? $this->repository->getAll($request)
            : ($request->has('user_id') ? $this->repository->getUsersJobs($request->user_id) : []);

        return self::success(data: $response, message: 'Bookings fetched successfully.');
    }

    public function show(Job $job)
    {
        return self::success(data: $job->load('translatorJobRel.user'), message: 'Booking details.');
    }

    public function store(StoreBookingRequest $request)
    {
        /*
        // Lazy loading userMeta relation
        */
        try {
            $response = $this->repository->store(auth()->user()->load('userMeta'), $request->validated());

            return self::success(data: $response, message: 'New Booking saved successfully.');
        } catch (Exception $e) {
            return self::exception(exception: $e);
        }
    }

    // using route model binding to get the model
    public function update(UpdateJobRequest $request, Job $job)
    {
        try {
            $response = $this->repository->updateJob($job->load('translatorJobRel'), Arr::except($request->validated(), ['_token', 'submit']), auth()->user());

            return self::success(data: $response, message: 'Job was updated successfully.');
        } catch (\Exception $e) {
            return self::exception(exception: $e);
        }
    }

    // using route model binding to get the model
    public function immediateJobEmail(Job $job, StoreJobEmailRequest $request)
    {
        try {
            $response = $this->repository->storeJobEmail($job->load('user.userMeta'), $request->validated());

            return self::success(data: $response, mesasge: 'Immediae Job Email sent successfully.');
        } catch (\Exception $e) {
            return self::exception(exception: $e);
        }
    }

    public function getHistory(GetJobHistoryRequest $request, User $user)
    {
        try {
            $response =  $request->has('user_id')
                ? $this->repository->getUsersJobsHistory($user, $request)
                : [];

            return self::success(data: $response, mesasge: 'History fetched successfully.');
        } catch (\Exception $e) {
            return self::exception(exception: $e);
        }
    }

    public function acceptJob(AcceptJobRequest $request, Job $job)
    {
        try {
            $response = $this->repository->acceptJob($job->load('user'), auth()->user()->load('userMeta'));

            return self::success(data: $response, message: 'Job was accepted succcessfully.');
        } catch (\Exception $e) {
            return self::exception(exception: $e);
        }
    }

    public function acceptJobWithId(Request $request)
    {
        $data = $request->get('job_id');
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJobWithId($data, $user);

        return response($response);
    }

    public function cancelJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->cancelJobAjax($data, $user);

        return response($response);
    }

    public function endJob(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->endJob($data);

        return response($response);
    }

    public function customerNotCall(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->customerNotCall($data);

        return response($response);
    }

    public function getPotentialJobs(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->getPotentialJobs($user);

        return response($response);
    }

    public function distanceFeed(Request $request)
    {
        $data = $request->all();

        if (isset($data['distance']) && $data['distance'] != "") {
            $distance = $data['distance'];
        } else {
            $distance = "";
        }
        if (isset($data['time']) && $data['time'] != "") {
            $time = $data['time'];
        } else {
            $time = "";
        }
        if (isset($data['jobid']) && $data['jobid'] != "") {
            $jobid = $data['jobid'];
        }

        if (isset($data['session_time']) && $data['session_time'] != "") {
            $session = $data['session_time'];
        } else {
            $session = "";
        }

        if ($data['flagged'] == 'true') {
            if ($data['admincomment'] == '') return "Please, add comment";
            $flagged = 'yes';
        } else {
            $flagged = 'no';
        }

        if ($data['manually_handled'] == 'true') {
            $manually_handled = 'yes';
        } else {
            $manually_handled = 'no';
        }

        if ($data['by_admin'] == 'true') {
            $by_admin = 'yes';
        } else {
            $by_admin = 'no';
        }

        if (isset($data['admincomment']) && $data['admincomment'] != "") {
            $admincomment = $data['admincomment'];
        } else {
            $admincomment = "";
        }
        if ($time || $distance) {

            $affectedRows = Distance::where('job_id', '=', $jobid)->update(array('distance' => $distance, 'time' => $time));
        }

        if ($admincomment || $session || $flagged || $manually_handled || $by_admin) {

            $affectedRows1 = Job::where('id', '=', $jobid)->update(array('admin_comments' => $admincomment, 'flagged' => $flagged, 'session_time' => $session, 'manually_handled' => $manually_handled, 'by_admin' => $by_admin));
        }

        return response('Record updated!');
    }

    public function reopen(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->reopen($data);

        return response($response);
    }

    public function resendNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $job_data, '*');

        return response(['success' => 'Push sent']);
    }

    public function resendSMSNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['success' => $e->getMessage()]);
        }
    }
}
