<?php

namespace Ma\AuthOtpApi\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Ma\AuthOtpApi\Models\UserOtp;

class SignUpOtpNotification extends Notification
{
    use Queueable;

    private $otp;

    private $isResend;

    private $expireAt;

    public $messageOptions;

    public $tries = 10;

    public $timeout = 10;

    # construct
    public function __construct($isResend = false)
    {
        $this->otp = mt_rand(100000, 999999);

        $this->isResend = $isResend;

        $this->expireAt = env('OTP_EXPIRE');

        $this->messageOptions = [
            'otp' => $this->otp,
            'minutes' => $this->expireAt,
            'code' => env('AUTO_OTP_ACCESS_CODE'),
            'app_name' => env('APP_NAME')
        ];
    }

    # choose the channel for sending sms
    public function via($notifiable)
    {
        $this->updateDatabase($notifiable);
        return [];
    }

    # update the users_otp table
    private function updateDatabase($notifiable)
    {
        if ($this->isResend == false) :

            $user_otp = new UserOtp();
            $user_otp->user_id = $notifiable->id;
            $user_otp->otp_type = UserOtp::SIGNUP_TYPE;
            $user_otp->otp_code = $this->otp;
            $user_otp->send_to_phone = $notifiable->phone;
            $user_otp->is_otp_verified = false;
            $user_otp->failed_attempts_count = 0;
            $user_otp->resend_count = 0;
            $user_otp->last_failed_attempt_date = null;
            $user_otp->last_resend_date = Carbon::now();
            $user_otp->verified_at = null;
            $user_otp->created_at = Carbon::now();
            $user_otp->save();


        else :
            $userSignupOtp = UserOtp::where('user_id', $notifiable->id)
                ->where('otp_type', UserOtp::SIGNUP_TYPE)
                ->where('is_otp_verified', false)
                ->first();

            $userSignupOtp->otp_code = $this->otp;
            $userSignupOtp->resend_count += 1;
            $userSignupOtp->last_resend_date = Carbon::now();
            $userSignupOtp->save();

        endif;
    }
}
