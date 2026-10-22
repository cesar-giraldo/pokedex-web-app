<?php

declare(strict_types=1);

namespace App\Web\Security;

final class GoogleOAuthSession
{
    public const string STATE = 'google_oauth_state';

    public const string LOCALE = 'google_oauth_locale';

    public const string RETURN_ROUTE = 'google_oauth_return_route';

    public const string CHECK_ROUTE = 'app_connect_google_check';

    public const string REGISTER_ROUTE = 'app_public_register';

    public const string LOGIN_ROUTE = 'app_public_login';
}
