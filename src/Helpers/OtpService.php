<?php

namespace Ma\AuthOtpApi\Helpers;

use Carbon\Carbon;

class OtpService
{
    private $userOtpModel;

    /**
     * Create new otp instance to manage otp workflow.
     *
     * OtpService constructor.
     * @param $userOtpModel
     */
    public function __construct($userOtpModel)
    {
        $this->userOtpModel = $userOtpModel;
    }

    /**
     * Check if otp is expired.
     *
     * @return bool
     */
    public function checkIsExpire(): bool
    {

        //get current time
        $currentTime = Carbon::now();

        //add allowed time before otp expire to current time
        $otpExpireAt = $this->userOtpModel->last_resend_date->addMinutes(30);

        //check if current time larger or equal to expire time
        if ($currentTime->gte($otpExpireAt)) {
            // otp is expired
            return true;
        } else {
            //otp is active
            return false;
        }
    }

    /**
     * Check if otp execute max attempts and reset the counter after suspend time is finish.
     *
     * @return bool
     */
    public function isExecuteMaxAttempts(): bool
    {

        //get latest failed date
        $failedData = $this->userOtpModel->last_failed_attempt_date;

        //get total failed count
        $failedAttempts = $this->userOtpModel->failed_attempts_count;

        //get max allowed attempts from env settings
        $maxAllowedAttempts = config('auth-otp-api.OTP_ATTEMPTS');;

        //check if allowed attempts large or equal to failed attempts at databases
        if ($failedAttempts >= $maxAllowedAttempts) {

            //get the difference in minutes between current time and failed time
            $period = Carbon::now()->diffInMinutes($failedData);

            //check current time large then suspend
            if ($period >= config('auth-otp-api.OTP_SUSPEND')) {

                //reset attempts after suspend time is finished
                $this->userOtpModel->last_failed_attempt_date = null;
                $this->userOtpModel->failed_attempts_count = 0;
                $this->userOtpModel->save();

                //not execute max attempts
                return false;
            } else {

                //yes its execute max attempts
                return true;
            }
        }

        //not execute max attempts
        return false;
    }

    /**
     * Check if otp execute max resend and reset the counter after suspend time is finish.
     *
     * @return bool
     */
    public function isExecuteMaxResend(): bool
    {

        //get count of user resend otp code
        $totalOtpResendToUser = $this->userOtpModel->resend_count;

        //get latest resend date
        $resendDate = $this->userOtpModel->last_resend_date;

        //get max resend count from settings
        $maxResendCount = config('auth-otp-api.OTP_RESEND');

        //check if max resend execute the limit of our settings
        if ($totalOtpResendToUser >= $maxResendCount) {

            //get the difference in minutes between current time and latest resend time
            $period = Carbon::now()->diffInMinutes($resendDate);

            //check current time large then suspend
            if ($period >= config('auth-otp-api.OTP_SUSPEND')) {

                //reset resend counter after suspend time is finished
                $this->userOtpModel->last_resend_date = null;
                $this->userOtpModel->resend_count = 0;
                $this->userOtpModel->save();

                //not execute max resend
                return false;
            } else {

                //yes its execute max resend
                return true;
            }
        }

        //not execute max resend
        return false;
    }

    /**
     * Check if otp resend request is too fast
     *
     * @return bool
     */
    public function isResendRequestTooFast()
    {

        //get latest resend date
        $resendDate = $this->userOtpModel->last_resend_date;

        //check if there is no resend date
        if ($resendDate == null) {
            //request is normal
            return false;
        }

        //get the difference in minutes between current time and latest resend time
        $period = Carbon::now()->diffInMinutes($resendDate);

        //get delay value from settings
        $delay = config('auth-otp-api.OTP_DELAY');

        //check if request send too fast before delay is finish
        if ($period < $delay) {
            //yes request is too fast
            return true;
        }

        //request is normal
        return false;
    }

    /**
     * Check if otp verify check is too fast
     *
     * @return bool
     */
    public function isVerifyCheckTooFast(): bool
    {

        //get latest failed date
        $failedDate = $this->userOtpModel->last_failed_attempt_date;

        //check if there is no failed date
        if ($failedDate == null) {
            //request is normal
            return false;
        }

        //get the difference in minutes between current time and latest failed time
        $period = Carbon::now()->diffInMinutes($failedDate);

        //get delay value from settings
        $delay = config('auth-otp-api.OTP_DELAY');

        //check if request send too fast before delay is finish
        if ($period < $delay) {
            //yes request is too fast
            return true;
        }

        //request is normal
        return false;
    }
}
