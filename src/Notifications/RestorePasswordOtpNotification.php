<?php

namespace Ma\AuthOtpApi\Notifications;


use Carbon\Carbon;
use Illuminate\Notifications\Notification;
use Ma\AuthOtpApi\Models\UserOtp;

class RestorePasswordOtpNotification extends Notification
{

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
        if ($this->isResend == false):

            UserOtp::create([
                'user_id' => $notifiable->id,
                'otp_type' => UserOtp::RESTORE_PASSWORD_TYPE,
                'otp_code' => $this->otp,
                'send_to_phone' => $notifiable->phone,
                'is_otp_verified' => false,
                'failed_attempts_count' => 0,
                'resend_count' => 0,
                'last_failed_attempt_date' => null,
                'last_resend_date' => Carbon::now(),
                'verified_at' => null,
                'created_at' => Carbon::now()
            ]);

        else:

            $userSignupOtp = UserOtp::where('user_id', $notifiable->id)
                ->where('otp_type', UserOtp::RESTORE_PASSWORD_TYPE)
                ->where('is_otp_verified', false)
                ->first();

            $userSignupOtp->otp_code = $this->otp;
            $userSignupOtp->resend_count += 1;
            $userSignupOtp->last_resend_date = Carbon::now();
            $userSignupOtp->save();

        endif;
    }
}
