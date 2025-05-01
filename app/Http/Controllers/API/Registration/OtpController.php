<?php


namespace App\Http\Controllers\API\Registration;
use \Illuminate\Http\Request;
use App\Http\Controllers\Email\EmailController;

class OtpController extends \App\Http\Controllers\Controller
{
    public static function NewOtpKey($Email)
    {
        if (trim($Email) !== null) {
            $DateTime = date("Y-m-d H:i:s", time());
            $OTP_KEY = md5($DateTime . $Email);
            return $OTP_KEY;
        }
        return null;
    }
    public static function EmailOtpKey($Email, $OTP_KEY)
    {
        $APP_URL = config('url_config.app_url');
        EmailController::AppSendEmail($Email, "Verify Your Email!", '<h3>Verify your email address</h3> <div>Click the link to verify your email address. <a href="' . $APP_URL . '/registration/verify_email/' . $OTP_KEY . '">' . $APP_URL . '/registration/verify_email/' . $OTP_KEY . '</a> </div>');

    }
}
