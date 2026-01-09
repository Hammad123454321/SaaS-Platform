<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Template;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class BirthdayWishNotification extends Notification
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function via($notifiable)
    {
        return [$this->data['channel']];
    }

    public function toMail($notifiable)
    {
        $general_settings = get_settings('general_settings');
        $full_logo = !isset($general_settings['full_logo']) || empty($general_settings['full_logo']) ? 'storage/logos/default_full_logo.png' : 'storage/' . $general_settings['full_logo'];
        $company_title = $general_settings['company_title'] ?? 'Taskify';
        $siteUrl = $general_settings['site_url'] ?? request()->getSchemeAndHttpHost();
        $fetched_data = Template::where('type', 'email')
            ->where('name', 'birthday_wish')
            ->first();

        $notification_data = $this->data['notification_data'];

        $subjectPlaceholders = [
            '{FIRST_NAME}' => $notification_data['first_name'],
            '{LAST_NAME}' => $notification_data['last_name'],
            '{BIRTHDAY_COUNT}' => $notification_data['birthday_count'],
            '{ORDINAL_SUFFIX}' => $notification_data['ordinal_suffix'],
            '{COMPANY_TITLE}' => $company_title
        ];

        $subject = filled(Arr::get($fetched_data, 'subject')) ? $fetched_data->subject : 'Happy Birthday - {COMPANY_TITLE}';

        $subject = str_replace(array_keys($subjectPlaceholders), array_values($subjectPlaceholders), $subject);

        $messagePlaceholders = [
            '{FIRST_NAME}' => $notification_data['first_name'],
            '{LAST_NAME}' => $notification_data['last_name'],
            '{BIRTHDAY_COUNT}' => $notification_data['birthday_count'],
            '{ORDINAL_SUFFIX}' => $notification_data['ordinal_suffix'],
            '{COMPANY_TITLE}' => $company_title,
            '{SITE_URL}' => $siteUrl,
            '{CURRENT_YEAR}' => date('Y')
        ];

        if (filled(Arr::get($fetched_data, 'content'))) {
            $emailTemplate = $fetched_data->content;
        } else {
            $defaultTemplatePath = resource_path('views/mail/default_templates/birthday_wish.blade.php');
            $defaultTemplateContent = File::get($defaultTemplatePath);
            $emailTemplate = $defaultTemplateContent;
        }

        $emailTemplate = str_replace(array_keys($messagePlaceholders), array_values($messagePlaceholders), $emailTemplate);

        return (new MailMessage)
            ->view('mail.html', ['content' => $emailTemplate, 'logo_url' => asset($full_logo)])
            ->subject($subject);
    }

    public function toSms($notifiable)
    {
        send_sms($notifiable, $this->data['notification_data']);
    }

    public function toWhatsApp($notifiable)
    {
        sendWhatsAppNotification($notifiable, $this->data['notification_data']);
    }

    //system here

    public function toPush($notifiable)
    {
        sendPushNotification($notifiable, $this->data['notification_data']);
    }

    public function toSlack($notifiable)
    {
        sendSlackNotification($notifiable, $this->data['notification_data']);
    }
}
