<?php


namespace Ma\AuthOtpApi\Http\Controllers;

use App\Http\Controllers\Controller;
use Ma\AuthOtpApi\Helpers\PhoneCleanerHelper;
use Ma\AuthOtpApi\Http\Requests\ChangePassword;
use Ma\AuthOtpApi\Http\Requests\Login;
use Ma\AuthOtpApi\Http\Requests\RequestPassword;
use Ma\AuthOtpApi\Http\Requests\RequestPasswordResendOTP;
use Ma\AuthOtpApi\Http\Requests\RequestPasswordVerifyOTP;
use Ma\AuthOtpApi\Http\Requests\Signup;
use Ma\AuthOtpApi\Http\Requests\SignupVerifyOTP;
use Ma\AuthOtpApi\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;
use Ma\AuthOtpApi\Helpers\OtpService;
use Ma\AuthOtpApi\Models\UserOtp;
use Ma\AuthOtpApi\Notifications\RestorePasswordOtpNotification;
use Ma\AuthOtpApi\Notifications\SignUpOtpNotification;
use Ma\AuthOtpApi\Traits\ResponseTrait;

class AuthController extends Controller
{
    use ResponseTrait;


    public function signup(Signup $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $phoneNumber = (new PhoneCleanerHelper($request->phone))->clean();

            if ($phoneNumber == false) {
                return $this->responseError(null, 'invalid phone number');
            }

            $user = new User();

            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone = $phoneNumber;
            $user->password = Hash::make($request->password);
            $user->save();

            $accessToken = $user->createToken('token')->plainTextToken;


            # send sms for otp
            $user->notify(new SignUpOtpNotification());

            DB::commit();



            return $this->responseSuccess([
                'access_token' => $accessToken,
                'user' => new UserResource($user)
            ], 'auth.signup_successfully');
        } catch (Exception $exception) {
            DB::rollBack();
            return $this->responseError($exception->getMessage(), 'auth.signup_failed', [], 500);
        }
    }

    public function login(Login $request)
    {
        try {
            $phoneNumber = (new PhoneCleanerHelper($request->phone))->clean();
            if ($phoneNumber == false) {
                return $this->responseError(null, 'invalid phone number');
            }

            $user = User::where('phone', $phoneNumber)->first();
            if (!$user) return $this->responseError(null, 'auth.login_wrong_password');

            if (!Hash::check($request->get('password'), $user->password)) {
                return $this->responseError(null, 'auth.login_wrong_password');
            }

            $accessToken = $user->createToken('token')->plainTextToken;

            return $this->responseSuccess([
                'access_token' => $accessToken,
                'user' => new UserResource($user)
            ], 'auth.login_successfully');
        } catch (\Exception $exception) {
            return $this->responseError(null, 'auth.login_wrong_password');
        }
    }

    public function verify(SignupVerifyOTP $request)
    {
        try {
            # start watching database changes.
            DB::beginTransaction();

            //get current login user model
            $userModel = Auth::user();

            # get user otp
            $userOtpModel = UserOtp::where('user_id', $userModel->id)
                ->where('otp_type', UserOtp::SIGNUP_TYPE)
                ->firstOrFail();

            # create new otp service instance
            $otpService = new OtpService($userOtpModel);

            # check if the user execute max attempts to verify his account
            if ($otpService->isExecuteMaxAttempts()) {
                return $this->responseError(null, 'otp.execute_max_verify_attempts', [
                    'minutes' => env('OTP_SUSPEND')
                ]);
            }

            # check if user input otp equal to otp saved at our database
            if ($userOtpModel->otp_code !== $request->get('otp')) {

                # if otp not correct save this operation at our database
                $userOtpModel->failed_attempts_count += 1;
                $userOtpModel->last_failed_attempt_date = Carbon::now();
                $userOtpModel->save();

                # apply database changes.
                DB::commit();

                return $this->responseError(null, 'auth.signup_verify_otp_failed');
            }

            # check if otp code is expire
            if ($otpService->checkIsExpire()) {
                return $this->responseError(null, 'otp.expired');
            }


            # make user verified by giving the verified at date now
            $userModel->update(['verified_at' => Carbon::now(), 'is_verified' => true]);

            # remove otp entity from otp table at our database
            $userOtpModel->delete();

            # apply database changes.
            DB::commit();

            return $this->responseSuccess(['user' => $userModel], 'auth.signup_verify_otp_succeed');
        } catch (Exception $e) {

            # if anything goes wrong rollback to old status.
            DB::rollBack();
            return $this->responseError(null, 'auth.signup_otp_is_already_verified');
        }
    }

    public function resend()
    {
        try {
            # get current login user
            $userModel = Auth::user();

            # get user otp
            $userOtpModel = UserOtp::where('user_id', $userModel->id)
                ->where('otp_type', UserOtp::SIGNUP_TYPE)
                ->firstOrFail();

            # create new otp service instance
            $otpService = new OtpService($userOtpModel);

            # check if the user execute max attempts to verify his account
            if ($otpService->isExecuteMaxAttempts()) {
                return $this->responseError(null, 'otp.execute_max_verify_attempts', [
                    'minutes' => env('OTP_SUSPEND')
                ]);
            }

            # check if the user execute max resend to get otp code
            if ($otpService->isExecuteMaxResend()) {
                return $this->responseError(null, 'otp.execute_max_resend', ['minutes' => env('OTP_SUSPEND')]);
            }

            # check if resend otp code is too fast
            if ($otpService->isResendRequestTooFast()) {
                return $this->responseError(null, 'otp.resend_is_too_fast', [
                    'minutes' => env('OTP_DELAY')
                ]);
            }

            # send otp code using sms to user phone
            $userModel->notify(new SignUpOtpNotification(true));

            return $this->responseSuccess(null, 'auth.signup_resend_otp_succeed', [
                'phone' => $userModel->phone
            ]);
        } catch (\Exception $e) {
            return $this->responseError(null, 'auth.signup_otp_is_already_verified');
        }
    }

    public function requestPassword(RequestPassword $request)
    {
        # format phone number check if its invalid
        $phoneNumber = (new PhoneCleanerHelper($request->get('phone')))->clean();
        if ($phoneNumber == false) {
            return $this->responseError(null, 'auth.invalid_phone_number');
        }

        # get user from database using phone number
        $user = User::wherePhone($phoneNumber)->first();

        if (!$user) return $this->responseError(null, 'auth.request_password_wrong_phone');

        # check if user already request restore password otp
        $isRequestPasswordBefore = UserOtp::where('send_to_phone', $phoneNumber)
            ->where('otp_type', UserOtp::RESTORE_PASSWORD_TYPE)
            ->where('is_otp_verified', false)
            ->exists();

        # if true then you already have otp or you need to resend it
        if ($isRequestPasswordBefore) {
            return $this->responseSuccess(null, 'auth.request_password_otp_already_sent');
        }

        # restorePassword is normal this is the first time
        $user->notify(new RestorePasswordOtpNotification());

        return $this->responseSuccess(null, 'auth.request_password_otp_sent', [
            'phone' => $phoneNumber
        ]);
        try {
        } catch (\Exception $e) {
            return $this->responseError(null, 'auth.request_password_wrong_phone');
        }
    }

    public function requestPasswordVerifyOtp(RequestPasswordVerifyOTP $request): JsonResponse
    {
        try {

            # start watching database changes.
            DB::beginTransaction();

            # format phone number check if its invalid
            $phoneNumber = (new PhoneCleanerHelper($request->get('phone')))->clean();
            if ($phoneNumber == false) {
                return $this->responseError(null, 'auth.invalid_phone_number');
            }

            # find otp code save at database with same phone number and same otp type
            $userOtpModel = UserOtp::where('send_to_phone', $phoneNumber)
                ->where('otp_type', UserOtp::RESTORE_PASSWORD_TYPE)
                ->firstOrFail();

            # create new otp service instance
            $otpService = new OtpService($userOtpModel);

            //check if the user execute max attempts to verify his account
            if ($otpService->isExecuteMaxAttempts()) {
                return $this->responseError(
                    null,
                    'otp.execute_max_verify_attempts',
                    ['minutes' => env('OTP_SUSPEND')]
                );
            }

            # check if user input same as otp code at our database
            if ($userOtpModel->otp_code !== $request->get('otp')) {

                # record failed attempts when code not same as our database
                $userOtpModel->failed_attempts_count += 1;
                $userOtpModel->last_failed_attempt_date = Carbon::now();
                $userOtpModel->save();

                # apply database changes.
                DB::commit();

                return $this->responseError(null, 'auth.request_password_verify_otp_failed');
            }

            # check if otp code is expire
            if ($otpService->checkIsExpire()) {
                return $this->responseError(null, 'otp.expired');
            }

            //get the user
            $user = User::find($userOtpModel->user_id);
            $accessToken = $user->createToken('token')->plainTextToken;


            # remove otp entity from otp table at our database,
            $userOtpModel->delete();

            # apply database changes.
            DB::commit();

            return $this->responseSuccess(["access_token" => $accessToken], 'auth.request_password_verify_otp_succeed');
        } catch (Exception $e) {

            # if anything goes wrong rollback to old status.
            DB::rollBack();

            return $this->responseError(null, 'auth.request_password_verify_otp_failed');
        }
    }

    public function requestPasswordResendOtp(RequestPasswordResendOTP $request): JsonResponse
    {
        try {

            # format phone number check if its invalid
            $phoneNumber = (new PhoneCleanerHelper($request->get('phone')))->clean();
            if ($phoneNumber == false) {
                return $this->responseError(null, 'auth.invalid_phone_number');
            }

            # get user otp model
            $userOtpModel = UserOtp::where('send_to_phone', $phoneNumber)
                ->where('otp_type', UserOtp::RESTORE_PASSWORD_TYPE)
                ->where('is_otp_verified', false)
                ->firstOrFail();

            # create new otp service instance
            $otpService = new OtpService($userOtpModel);

            # check if the user execute max attempts to request password
            if ($otpService->isExecuteMaxAttempts()) {
                return $this->responseError(null, 'otp.execute_max_verify_attempts', ['minutes' => env('OTP_SUSPEND')]);
            }

            # check if the user execute max resend to get otp code
            if ($otpService->isExecuteMaxResend()) {
                return $this->responseError(null, 'otp.execute_max_resend', ['minutes' => env('OTP_SUSPEND')]);
            }

            # check if resend otp code is too fast
            if ($otpService->isResendRequestTooFast()) {
                return $this->responseError(null, 'otp.resend_is_too_fast', ['minutes' => env('OTP_DELAY')]);
            }

            # send otp code using sms to user phone
            User::find($userOtpModel->user_id)->notify(new RestorePasswordOtpNotification(true));

            return $this->responseSuccess(null, 'auth.request_password_resend_otp_succeed', [
                'phone' => $phoneNumber
            ]);
        } catch (Exception $e) {
            return $this->responseError(null, 'auth.request_password_resend_not_request_otp_before');
        }
    }

    public function changePassword(ChangePassword $request): JsonResponse
    {
        try {

            # start watching database changes.
            DB::beginTransaction();

            # update user password
            $user = Auth::user();
            $user->password = bcrypt($request->get('password'));
            $user->save();

            # remove user access tokens
            $token = $user->tokens()->delete();
            
            $accessToken = $user->createToken('token')->plainTextToken;

            # apply database changes.
            DB::commit();

            return $this->responseSuccess([
                'access_token' => $accessToken,
                'user' => new UserResource($user)
            ], 'auth.password_changed_successfully');
        } catch (Exception $e) {

            # if anything goes wrong rollback to old status.
            DB::rollBack();

            return $this->responseError(null, $e->getMessage());
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {

            # get oauth tokens refresh and access
            $token = $request->user()->token();

            # remove token
            $token->revoke();

            return $this->responseSuccess(null, 'auth.logout_successfully');
        } catch (Exception $e) {
            return $this->responseError(null, 'messages.request_failed');
        }
    }
}
