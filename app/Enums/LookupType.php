<?php

namespace App\Enums;

enum LookupType: string
{
    case Ip = 'ip';
    case Domain = 'domain';
    case Email = 'email';
}
