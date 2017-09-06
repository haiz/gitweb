<?php

//================= COMMON FUNCTIONS ===============
function auth() {
    return isset($_SESSION["_EMAIL"]) ? $_SESSION["_EMAIL"] : '';
}

function login($email, $password)
{
    $str = file_get_contents(PASSWD_FILE_PATH);
    $users = explode("\n", $str);
    $users = array_filter($users);
    foreach ($users as $user) {
        $info = explode(' ', $user, 2);
        if (count($info) < 2) {
            continue;
        }
        if ($email == trim($info[0]) && password_verify($password, $info[1])) {
            $_SESSION["_EMAIL"] = $email;
            addLog('login');
            return true;
        }
    }
    return false;
}

function logout() {
    if (isset($_SESSION["_EMAIL"])) {
        addLog('logout');
        unset($_SESSION["_EMAIL"]);
    }
}

function getUser()
{
    static $user = null;
    if ($user === null) {
        $user = (object) null;
        $user->email = isset($_SESSION['_EMAIL']) ? $_SESSION['_EMAIL'] : '';
    }
    return $user;
}

function addLog($message, $info = array())
{
    $user = getUser();
    $email = $user->email ? $user->email : 'GUEST';
    $log_file = APP_PATH . 'logs/' . date('Y-m-d') . '.log';
    $content = date('c') . ' ' . $email . ' ' . $message . ($info ? ' ' . json_encode($info) : '');
    @file_put_contents($log_file, $content . "\n", FILE_APPEND | LOCK_EX);
}

function getUserRepos($email)
{
    global $REPOS;
    $user_repos = array();
    foreach ($REPOS as $repo_key => $repo_config) {
        if ($perms = getPerms($email, $repo_config['users'])) {
            $user_repos[$repo_key] = array(
                'key' => $repo_key,
                'title' => isset($repo_config['title']) ? $repo_config['title'] : key2Title($repo_key),
                'path' => $repo_config['path'],
                'perms' => $perms,
                'info' => getRepoInfo($repo_config['path'])
            );
        }
    }
    return $user_repos;
}

function getUserRepo($email, $repo_key)
{
    global $REPOS;
    if (isset($REPOS[$repo_key]) && $perms = getPerms($email, $REPOS[$repo_key]['users'])) {
        return array(
            'key' => $repo_key,
            'title' => isset($REPOS[$repo_key]['title']) ? $REPOS[$repo_key]['title'] : key2Title($repo_key),
            'path' => $REPOS[$repo_key]['path'],
            'perms' => $perms,
            'info' => getRepoInfo($REPOS[$repo_key]['path'])
        );
    }
    return false;
}

function getPerms($email, $repo_users)
{
    global $ADMINS;
    if (in_array($email, $ADMINS)) {
        return GIT_FULL_PERMS;
    }
    if (isset($repo_users[$email])) {
        return $repo_users[$email];
    }
    return false;
}

function checkPerm($perm, $perms)
{
    if ( ! $perms) {
        return false;
    }
    if ($perm == GIT_VIEW) {
        return true;
    }
    if ($perms == GIT_FULL_PERMS ||
        $perms == $perm ||
        (is_array($perms) && (
            in_array(GIT_FULL_PERMS, $perms) ||
            in_array($perm, $perms))))
    {
        return true;
    }
    return false;
}

function hasPerm($repo_key, $perm)
{
    global $REPOS;
    $user = getUser();
    if ( ! $user->email) {
        return false;
    }
    $perms = getPerms($user->email, $REPOS[$repo_key]['users']);
    return checkPerm($perm, $perms);
}

function getRepoInfo($repo_path)
{
    return array(
        'remote_branches' => gitBranches($repo_path),
        'current_branch' => gitCurrentBranch($repo_path),
        'current_commit' => gitCurrentCommit($repo_path),
        'last_check' => time()
    );
}


function gitCurrentBranch($path)
{
    // $cmd = 'cd ' . $path . ' && '. GIT_BIN .' branch | grep \\*';
    // $output = shell_exec($cmd);
    $output = doGit($path, 'branch | grep \\*');
    return trim($output, "\n\r\t *");
}

function gitCurrentCommit($path)
{
    // $cmd = 'cd ' . $path . ' && '. GIT_BIN .' log -1';
    // $output = shell_exec($cmd);
    $output = doGit($path, 'log -1');
    $parts = explode("\n", $output, 5);
    $part_count = 5;

    $commit = array_shift($parts);
    $commit = explode(' ', $commit);
    $commit = array_pop($commit);

    $merge = array_shift($parts);
    $merge = explode(':', $merge, 2);
    if (strtolower($merge[0]) === 'merge') {
        $merge = array_pop($merge);
        $author = explode(':', array_shift($parts), 2);
    }
    else {
        $author = $merge;
        $merge = '';
        $part_count = 4;
    }

    $author = trim(array_pop($author));

    $date = array_shift($parts);
    $date = explode(':', $date, 2);
    $date = trim(array_pop($date));

    $message = ($part_count==4) ? implode("\n", $parts) : array_shift($parts);
    $ret = array(
        'commit' => $commit
    );
    if ($merge) {
        $ret['merge'] = $merge;
    }
    $ret['author'] = $author;
    $ret['date'] = $date;
    $ret['message'] = trim($message);

    return $ret;
}


function doGits($cmds, $repo, $params)
{
    ;
    if ( ! checkPerm($params['need_perm'], $repo['perms'])) {
        _error("You don't have permission to fetch the repo " . $repo['title']);
    }
    if ( ! is_dir($repo['path'])) {
        _error('Repo directory is not exist');
    }
    $outputs = array();
    foreach ($cmds as $cmd) {
        switch ($cmd) {
            case 'fetch':
                $outputs[] = gitFetch($repo, $params);
                break;
            case 'pull':
                $outputs[] = gitPull($repo, $params);
                break;
            case 'checkout':
                $outputs[] = gitCheckout($repo, $params);
                break;
        }
    }
    $outputs = array_filter($outputs);
    $ret = array(
        'message' => implode(' then ', array_map('key2Title', $cmds)) . ' successfully',
        'repo' => getUserRepo($params['email'], $repo['key']),
        'outputs' => $outputs
    );
    _success($ret);
}

function doGit($path, $git_cmd) {
    $cmd = 'cd ' . $path . ' && '. GIT_BIN .' '. $git_cmd .' 2>&1';
    return shell_exec($cmd);
    // $ret = shell_exec($cmd);
    // var_dump($cmd);
    // var_dump($ret);
    // return $ret;
    // // exit();
}

function gitFetch($repo) {
    addLog('fetch repo ' . $repo['title']);
    return doGit($repo['path'], 'fetch');
}

function gitPull($repo) {
    addLog('pull repo ' . $repo['title']);
    return doGit($repo['path'], 'pull');
}

function gitCheckout($repo, $params) {
    addLog('checkout repo ' . $repo['title']);
    return doGit($repo['path'], 'checkout ' . $params['branch']);
}

// List all remote branches
function gitBranches($path)
{
    $branches = array();
    // $cmd = 'cd ' . $path . ' && '. GIT_BIN .' branch -a --sort=-committerdate 2>&1';
    // $output = shell_exec($cmd);
    $output = doGit($path, 'branch -a --sort=-committerdate');
    $tmp_branches = explode("\n", $output);
    foreach ($tmp_branches as $branch) {
        if (preg_match('#remotes/origin/(.*)$#', $branch, $matches))
        {
            if (strpos($matches[1], 'HEAD') !== 0) {
                $branches[] = trim($matches[1]);
            }
        }
    }
    return $branches;
}

function isPost() {
    return $_SERVER['REQUEST_METHOD'] == 'POST';
}

function isGet() {
    return $_SERVER['REQUEST_METHOD'] == 'GET';
}

function getPost($var, $default = null) {
    return isset($_POST[$var]) ? $_POST[$var] : $default;
}

function getQuery($var, $default = null) {
    return isset($_GET[$var]) ? $_GET[$var] : $default;
}

function redirect($uri = '')
{
    header("Location: " . $uri, TRUE, 302);
    exit;
}

function key2Title($key)
{
    $key = preg_replace('/[^A-Za-z0-9]/', ' ', $key);
    $words = explode(' ', $key);
    $words = array_filter($words);
    $title = '';
    foreach ($words as $word) {
        $title .= ($title ? ' ' : '') . ucfirst($word);
    }
    return $title;
}

//================= AJAX COMMON FUNCTIONS =================
//
function isAjax()
{
    if( ! empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        return true;
    }
    return false;
}

function _jsonResponse($response, $code = 200, $message = 'OK')
{
    header("HTTP/1.0 $code $message");
    header("Content-type: application/json");
    echo json_encode($response);
    exit();
}

function _success($response = null)
{
    _jsonResponse($response);
}

function _error($error, $code = '400', $message = 'Bad Request')
{
    $rp = (object) null;
    $rp->status = 0;
    $rp->message = $error;
    _jsonResponse($rp, $code, $message);
}

//======================== TEMPLATE FUNCTIONS ===================
function partial($file, $vars = '') {
    ob_start();
    render($file, $vars);
    return ob_get_clean();
}

function render($file, $vars = '') {
    if (is_array($vars)){
        extract($vars);
    }
    require 'src/views/' . $file;
}


