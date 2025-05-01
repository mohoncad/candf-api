<?php

namespace App\Http\Controllers\Email;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    public static function AppSendEmail($EmailTo, $EmailSubject, $EmailMessage)
    {
         Mail::send([], [], function ($message) use ($EmailTo, $EmailSubject, $EmailMessage) {
             $email = env('MAIL_FROM_ADDRESS') ?? "nayeem@dotlogic.xyz";
             $company = env('MAIL_FROM_NAME') ?? "C&F";

             $message->to($EmailTo)
                 ->from($email, $company)
                 ->subject($EmailSubject)
                 ->setBody($EmailMessage, 'text/html');
         });
    }
}
