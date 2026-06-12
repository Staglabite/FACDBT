<?php

namespace App\Enums;

enum MenuFlag: string
{
    case MASTER = 'Master Data';
    case BASIC = 'Basic Data';
    case TRANS = 'Transactions';
    case SETTING = 'Settings';
    case REPORT = 'Report';
}
