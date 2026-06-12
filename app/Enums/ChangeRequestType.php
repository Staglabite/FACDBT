<?php

namespace App\Enums;

enum ChangeRequestType: string
{
    case ADD = 'Add';
    case UPDATE = 'Update';
    case DELETE = 'Delete';
}
