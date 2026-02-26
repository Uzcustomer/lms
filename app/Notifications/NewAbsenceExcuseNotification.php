<?php

namespace App\Notifications;

use App\Models\AbsenceExcuse;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewAbsenceExcuseNotification extends Notification
{
    use Queueable;

    public function __construct(
        public AbsenceExcuse $excuse
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'excuse_id' => $this->excuse->id,
            'student_name' => $this->excuse->student_full_name,
            'group_name' => $this->excuse->group_name,
            'reason' => $this->excuse->reason_label,
            'start_date' => $this->excuse->start_date->format('d.m.Y'),
            'end_date' => $this->excuse->end_date->format('d.m.Y'),
            'message' => $this->excuse->student_full_name . ' yangi sababli ariza yubordi (' . $this->excuse->reason_label . ')',
        ];
    }
}
