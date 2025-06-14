<?php

namespace App\Controller;

use App\Service\Email\Message\EmailSendMessage;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{

    public function __construct(
        private MessageBusInterface $bus,
    )
    {
    }

    #[Route('/')]
    public function index(): Response
    {

        $email = (new Email())
            ->from('fabien@symfony.com')
            ->to('foo@example.com')
            ->cc('bar@example.com')
            ->bcc('baz@example.com')
            ->replyTo('fabien@symfony.com')
            ->priority(Email::PRIORITY_HIGH)
            ->subject('Important Notification')
            ->text('Lorem ipsum...')
            ->html('<h1>Lorem ipsum</h1> <p>...</p>');

        $emailMessage = new EmailSendMessage($email->toString());

        $this->bus->dispatch($emailMessage, [
            new AmqpStamp('email.transactional')
        ]);

        return new Response('all good');
    }


    public function oldIndex(): Response
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