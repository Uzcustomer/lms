<?php

namespace App\Enums;

enum RetakeLogAction: string
{
    case SUBMITTED = 'submitted';
    case RESUBMITTED = 'resubmitted';
    case DEAN_APPROVED = 'dean_approved';
    case DEAN_REJECTED = 'dean_rejected';
    case REGISTRAR_APPROVED = 'registrar_approved';
    case REGISTRAR_REJECTED = 'registrar_rejected';
    case ACADEMIC_DEPT_APPROVED = 'academic_dept_approved';
    case ACADEMIC_DEPT_REJECTED = 'academic_dept_rejected';
    case ASSIGNED_TO_GROUP = 'assigned_to_group';
    case PERIOD_OPENED = 'period_opened';
    case PERIOD_CLOSED = 'period_closed';
    case PERIOD_UPDATED = 'period_updated';
}
