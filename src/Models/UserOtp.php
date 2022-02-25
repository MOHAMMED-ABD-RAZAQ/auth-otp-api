<?php

namespace Ma\AuthOtpApi\Models;

use Illuminate\Database\Eloquent\Model;

class UserOtp extends Model
{
    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = null;

    /**
     * Predefined otp types.
     *
     * @var int
     */
    public const SIGNUP_TYPE = 0;

    public const RESTORE_PASSWORD_TYPE = 1;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "user_otps";

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'otp_type',
        'otp_code',
        'send_to_phone',
        'is_otp_verified',
        'failed_attempts_count',
        'last_failed_attempt_date',
        'last_resend_date',
        'verified_at',
        'created_at',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'last_failed_attempt_date',
        'last_resend_date',
        'verified_at',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = [ "created_at" ];
}
