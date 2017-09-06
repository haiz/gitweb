<?php

include 'src/config.php';
include 'src/functions.php';

session_start();

$email = auth();

$params = array(
    'email' => $email
);

if ( ! $email) {
    if (isAjax()) {
        _error('Unauthenticate', 401);
    }
    else {
        $params = array();
        if (isPost()) {
            $email = getPost('email', '');
            $password = getPost('password', '');
            if (login($email, $password)) {
                redirect('/git');
            }
            $params['login_email'] = $email;
            $params['error'] = 'Invalid email or password';
        }
        render('login.phtml', $params);
        exit();
    }
}
else if (isGet()) {
    $is_logout = getQuery('logout');
    if ($is_logout) {
        logout();
        redirect('/git');
    }
    if (isAjax()) {
        _success(getUserRepos($email));
    }
}
else if (isAjax() && isPost()) {
    $cmd = getPost('cmd', '');
    $repo_key = getPost('repo_key', '');
    if ( ! $repo_key) {
        _error('Invalid repo param');
    }
    if ( ! $repo = getUserRepo($email, $repo_key)) {
        _error('Repo not found');
    }

    $cmds = array($cmd);
    switch ($cmd) {
        case 'fetch':
            $params['need_perm'] = GIT_FETCH;
            doGits($cmds, $repo, $params);
            break;
        case 'pull':
            $params['need_perm'] = GIT_PULL;
            doGits($cmds, $repo, $params);
            break;
        case 'checkout':
            $branch = getPost('branch', '');
            if ( ! $branch) {
                _error('Invalid branch param');
            }
            $params['need_perm'] = GIT_CHECKOUT;
            $params['branch'] = $branch;
            doGits($cmds, $repo, $params);
            break;
        case 'checkout_pull':
            $branch = getPost('branch', '');
            if ( ! $branch) {
                _error('Invalid branch param');
            }
            $params['need_perm'] = GIT_CHECKOUT;
            $params['branch'] = $branch;
            $cmds = array('checkout', 'pull');
            doGits($cmds, $repo, $params);
            break;
        default:
            _error('Invalid command');
            break;
    }
}
$params['repos'] = getUserRepos($email);

render('index.phtml', $params);




