<?php

namespace App\Services;

use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\Mail;
use App\Notifications\BirthdayWishNotification;
use App\Notifications\AnniversaryWishNotification;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SmsNotification;
use App\Notifications\WhatsappNotification;
use App\Notifications\SlackNotification;

class NotificationService
{
    // Sends notifications based on the type (birthday, anniversary, etc.) and channels
    public function sendNotification($type)
    {
        $today = now()->toDateString();

        switch ($type) {
            case 'birthday':
                $this->sendBirthdayWishes($today);
                break;

            case 'anniversary':
                $this->sendAnniversaryWishes($today);
                break;

            default:
                // Handle default case
                break;
        }
    }

    // Send birthday wishes via multiple channels
    private function sendBirthdayWishes($today)
    {
        $users = User::whereDate('dob', $today)->get();
        foreach ($users as $user) {
            // Send Email
            Notification::send($user, new BirthdayWishNotification($user));

            // Send SMS
            Notification::route('nexmo', $user->phone)->notify(new SmsNotification($user));

            // Send WhatsApp (custom notification)
            Notification::route('whatsapp', $user->phone)->notify(new WhatsappNotification($user));

            // Send Slack (if user has Slack)
            if ($user->slack_id) {
                Notification::route('slack', $user->slack_id)->notify(new SlackNotification($user));
            }
        }
    }

    // Send work anniversary wishes via multiple channels
    private function sendAnniversaryWishes($today)
    {
        $clients = Client::whereDate('doj', $today)->get();
        foreach ($clients as $client) {
            // Send Email
            Notification::send($client, new AnniversaryWishNotification($client));

            // Send SMS
            Notification::route('nexmo', $client->phone)->notify(new SmsNotification($client));

            // Send WhatsApp (custom notification)
            Notification::route('whatsapp', $client->phone)->notify(new WhatsappNotification($client));

            // Send Slack (if client has Slack)
            if ($client->slack_id) {
                Notification::route('slack', $client->slack_id)->notify(new SlackNotification($client));
            }
        }
    }
}
