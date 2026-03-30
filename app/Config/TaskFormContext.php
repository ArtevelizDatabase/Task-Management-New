<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Konteks form task: Proyek vs Vendor/pribadi (saling eksklusif).
 */
class TaskFormContext extends BaseConfig
{
    public string $postKey = 'task_context';

    public string $contextProject = 'project';

    public string $contextVendor = 'vendor';

    /** @var list<string> */
    public array $eavKeysDisabledInProjectMode = [
        'account',
    ];
}
