<?php

namespace App\Controller;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{

    #[Route('/')]
    public function index(): Response
    {

        $logs = '';

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'hyvor-service-mailpit';
        // $mail->Port = 25;
        $mail->Port = 1025;
        $mail->SMTPDebug = SMTP::DEBUG_LOWLEVEL;
        $mail->Debugoutput = function ($str, $level) use (&$logs) {
            $logs .= "[$level] $str\n";
        };

        $mail->setFrom('supunkavinda1125@gmail.com', 'SUPUN.IO');
        $mail->addAddress('me@supun.io', 'Supun');
        $mail->Subject = 'Here is the subject';
        $mail->Body = 'This is the HTML message body <b>in bold!</b>';
        $mail->XMailer = 'Hyvor Relay';

        try {
            $mail->send();
        } catch (Exception $e) {
            dd($mail->ErrorInfo, $e->getMessage(), $logs);
        }

        dd($logs);

        return new Response($logs);

    }

}