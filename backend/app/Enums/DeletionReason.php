<?php

namespace App\Enums;

enum DeletionReason: string
{
    case USER_REQUEST = 'user_request';
    case RETENTION_EXPIRED = 'retention_expired';
    case ACCOUNT_DELETED = 'account_deleted';
}