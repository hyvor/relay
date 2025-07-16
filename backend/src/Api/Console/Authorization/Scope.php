<?php

namespace App\Api\Console\Authorization;

enum Scope: string
{
    case SENDS_READ = 'sends.read';
    case SENDS_WRITE = 'sends.write';
    case SENDS_SEND = 'sends.send';

    case DOMAINS_READ = 'domains.read';
    case DOMAINS_WRITE = 'domains.write';

    case WEBHOOKS_READ = 'webhooks.read';
    case WEBHOOKS_WRITE = 'webhooks.write';

    case API_KEYS_READ = 'api_keys.read';
    case API_KEYS_WRITE = 'api_keys.write';

    case SUPPRESSIONS_READ = 'suppressions.read';
    case SUPPRESSIONS_WRITE = 'suppressions.write';

    case ANALYTICS_READ = 'analytics.read';
    case PROJECTS_READ = 'projects.read';

    case PROJECTS_WRITE = 'projects.write';
}
