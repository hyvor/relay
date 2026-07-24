<?php

namespace App\Api\Console\Authorization;

use Hyvor\Internal\CloudApi\Scope\ScopeInterface;

enum Scope: string implements ScopeInterface
{

    // org-level
    case ORG_PROJECTS_CREATE = 'org.projects.create';

    // all users must have this scope (api keys may not)
    case PROJECT_READ = 'project.read';
    // to update project settings
    case PROJECT_WRITE = 'project.write';

    case SENDS_READ = 'sends.read';
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

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return array_map(fn($scope) => $scope->value, Scope::cases());
    }

    /**
     * @param Scope[] $except
     * @return string[]
     */
    public static function allExcept(array $except): array
    {
        return array_values(
            array_diff(
                self::all(),
                array_map(fn($scope) => $scope->value, $except)
            )
        );
    }
}
