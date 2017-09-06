<?php

define('APP_PATH', '/data/apps/gitweb/');

define('GIT_FULL_PERMS',    'all');
define('GIT_VIEW',        'access');
define('GIT_FETCH',         'fetch');
define('GIT_PULL',          'pull');
define('GIT_CHECKOUT',      'checkout');

define('GIT_BIN', '/usr/local/git/bin/git');

define('PASSWD_FILE_PATH', '/etc/git_web_users');

$ADMINS = array(
    'zeno@gmail.com',
);

$REPOS = array(
    'songoku' => array(
        'path' => '/data/apps/songoku',
        'users' => array(
            'viet@gmail.com' => GIT_FULL_PERMS,
            'nam@gmail.com' => array(GIT_VIEW))
    ),
    'vegeta' => array(
        'path' => '/data/apps/vegeta',
        'users' => array(
            'viet@gmail.com' => GIT_FULL_PERMS,
            'name@gmail.com' => array(GIT_VIEW))
    ),
    'songohan' => array(
        'path' => '/data/apps/songohan',
        'users' => array(
            'name@gmail.com' => GIT_FULL_PERMS)
    ),
);
