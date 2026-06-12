<?php

namespace App\Enums;

enum ChangeRequestStatus: string
{
    case DRAFT = 'Draft';
    case SUBMIT = 'Submit';
    case CANCEL = 'Cancel';
}
