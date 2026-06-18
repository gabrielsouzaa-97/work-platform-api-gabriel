<?php

declare(strict_types=1);

namespace App\Modules\Integration\Dto;

enum OccPassthroughOperation: string
{
    case SetQuota = 'set_quota';
    case SetQuotaDefault = 'set_quota_default';
    case QuotaAudit = 'quota_audit';
    case SetBranding = 'set_branding';
    case ToggleMaintenance = 'toggle_maintenance';
    case FilesRescan = 'files_rescan';
    case AppEnable = 'app_enable';
    case UserList = 'user_list';
}
