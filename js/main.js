
setTimeout(function () {doRefresh();}, REFRESH_TIME);

$('#modal-output').on('hidden.bs.modal', function () {
    updateLastRepo();
});


function gitPull(repo_key)
{
    if (REPOS[repo_key].current_branch != REPOS[repo_key].new_branch) {
        if (confirm('['+ REPOS[repo_key].title +'] You selected another branch. Do you want checkout it before pull?')) {
            gitChekoutPull(repo_key);
        }
    }
    else {
        var params = {
            'cmd': 'pull',
            'repo_key': repo_key,
            'branch': REPOS[repo_key].new_branch
        }
        doGit(params, 'Pulling...');
    }
}

function gitFetch(repo_key)
{
    var params = {
        'cmd': 'fetch',
        'repo_key': repo_key,
        'branch': REPOS[repo_key].new_branch
    }
    doGit(params, 'Fetching...');
}

function gitCheckout(repo_key)
{
    if (REPOS[repo_key].current_branch == REPOS[repo_key].new_branch) {
        alert('Already Checkouted');
    }
    else if (confirm('['+ REPOS[repo_key].title +'] Are you sure to checkout the branch: ' + REPOS[repo_key].new_branch)){
        var params = {
            'cmd': 'checkout',
            'repo_key': repo_key,
            'branch': REPOS[repo_key].new_branch
        }
        doGit(params, 'Checking out ...');
    }
}

function gitCheckoutPull(repo_key)
{
    if (REPOS[repo_key].current_branch == REPOS[repo_key].new_branch) {
        getPull(repo_key);
    }
    else if (confirm('['+ REPOS[repo_key].title +'] Are you sure to checkout then pull the branch: ' + REPOS[repo_key].new_branch)){
        var params = {
            'cmd': 'checkout_pull',
            'repo_key': repo_key,
            'branch': REPOS[repo_key].new_branch
        }
        doGit(params, 'Checking out and Pulling...');
    }
}

function doRefresh()
{
    var updating = false;
    // Dont do refresh if there is a busy repo
    $.each(REPOS, function (repo_key, repo) {
        if (repo.updating) {
            updating = true;
        }
    });
    if (updating) {
        setTimeout(function () {doRefresh();}, REFRESH_TIME);
        return true;
    }

    $.get('/git/')
    .done(function (rs) {
        // console.log('refresh done:', rs);
        $.each(rs, function(repo_key, repo) {
            if (repo.info.last_check <= REPOS[repo.key].last_check) {
                return false;
            }
            var updated = updateRepo(repo);
            if (updated) {
                notice('Repo [' + repo.title + '] was updated by another');
                // alert('Repo [' + repo.title + '] was updated by another');
            }
        });
    })
    .fail(function (rs) {
        // console.log('refresh fail:', rs);
    })
    .always(function (rs) {
        setTimeout(function () {doRefresh();}, REFRESH_TIME);
    });
}

function notice(msg) {
    if ( ! ALERTING) {
        ALERTING = true;
        setTimeout(function () {
            alert(msg);
            ALERTING = false;
        }, 0);
    }
}

function doGit(params, msg)
{
    var repo_key = params['repo_key'];
    hideAllButtons(repo_key);
    $('#' + getBranchSelectionKey(repo_key)).prop('disabled', true);
    if (msg) {
        $('#' + getButtonKey(repo_key, 'gitting')).text(msg);
        showButtons(repo_key, ['gitting']);
    }

    REPOS[repo_key].updating = true;
    $.post('/git/', params)
    .done(function (rs) {
        LAST_REPO = rs.repo;
        var outputs = (rs.outputs && rs.outputs.length > 0) ? rs.outputs : ['Nothing'];
        showGitActionOutput(repo_key, outputs);
        // console.log('Done:', rs);
    })
    .fail(function (rs) {
        // console.log('Fail:', rs)
    })
    .always(function (rs) {
        hideButtons(repo_key, ['gitting']);
        toggleButtons(repo_key);
        $('#' + getBranchSelectionKey(repo_key)).prop('disabled', false);

        REPOS[repo_key].updating = false;
    });
}

function showGitActionOutput(repo_key,  outputs)
{
    var text = '';
    $('#modal-output .modal-title span').text(REPOS[repo_key].title);
    var mb = $('#modal-output').find('.modal-body');
    for (var i = 0; i < outputs.length; i++) {
        content = $('<pre>'+ outputs[i] +'</pre>');
        if (i > 0) {
            mb.append($('<hr>'));
        }
        mb.append(content);
    }
    $('#modal-output').modal();
}

function updateLastRepo()
{
    if (LAST_REPO) {
        updateRepo(LAST_REPO);
        LAST_REPO = null;
    }
}

function updateRepo(repo)
{
    if (REPOS[repo.key].last_check < repo.info.last_check) {
        REPOS[repo.key].last_check = repo.info.last_check;
    }

    // Check if it is diffrent
    if (REPOS[repo.key].current_branch == repo.info.current_branch &&
        REPOS[repo.key].current_commit == repo.info.current_commit.commit &&
        REPOS[repo.key].total_remote_branches == repo.info.remote_branches.length)
    {
        return false;
    }
    showRepoInfo(repo);
    // The setting data is always after show repo information
    REPOS[repo.key].current_branch = repo.info.current_branch;
    REPOS[repo.key].new_branch = repo.info.current_branch;
    REPOS[repo.key].current_commit = repo.info.current_commit.commit;
    REPOS[repo.key].total_remote_branches = repo.info.remote_branches.length;

    toggleButtons(repo.key);

    return true;
}

function showRepoInfo(repo)
{
    var cbranch = repo.info.current_branch;
    if (cbranch !== REPOS[repo.key].current_branch) {
        var a = $('#' + getCurrentBranchKey(repo.key))
        a.unbind('click');
        a.bind('click', function() {
            resetBranch(repo.key, cbranch);
        });
        a.text(repo.info.current_branch);
    }

    var ttbranches = repo.info.remote_branches.length;
    if (cbranch !== REPOS[repo.key].current_branch ||
        ttbranches !== REPOS[repo.key].total_remote_branches)
    {
        var s = $('#' + getBranchSelectionKey(repo.key));
        s.html('');
        if (cbranch.indexOf('(HEAD ') === 0) {
            s.append('<option value="'+ cbranch +'">'+ cbranch +'</option>');
            s.append('<option disabled> --------- </option>');
        }
        for (var i = 0; i < repo.info.remote_branches.length; i++) {
            var option = $('<option value="'+ repo.info.remote_branches[i] +'">'+ repo.info.remote_branches[i] +'</option>');
            if (repo.info.remote_branches[i] == cbranch) {
                option.prop('selected', true);
            }
            s.append(option);
        }
    }

    var ccommit = repo.info.current_commit.commit;
    if (ccommit !== REPOS[repo.key].current_commit) {
        var c = $('#' + getCurrentCommitKey(repo.key));
        c.html('');

        $.each(repo.info.current_commit, function(p, v) {
            var d = $('<div class="row">');
            var label = p.charAt(0).toUpperCase();
            label += p.substr(1);
            d.append($('<label class="col-sm-2 control-label text-right">'+ label +'</label>'));
            d.append($('<div class="col-sm-10" style="word-wrap:break-word;">'+ v +'</div>'));
            c.append(d);
        });
    }
}

function resetBranch(repo_key, branch)
{
    $('#' + getBranchSelectionKey(repo_key)).val(branch);
    selectBranch(repo_key, branch);
}

function selectBranch(repo_key, new_branch)
{
    REPOS[repo_key].new_branch = new_branch;
    toggleButtons(repo_key);
}

function hideAllButtons(repo_key)
{
    hideButtons(repo_key, ['pull', 'fetch', 'checkout', 'checkout_pull']);
}

function toggleButtons(repo_key)
{
    if (REPOS[repo_key].current_branch == REPOS[repo_key].new_branch) {
        showButtons(repo_key, ['pull', 'fetch']);
        hideButtons(repo_key, ['checkout', 'checkout_pull']);
    }
    else {
        hideButtons(repo_key, ['pull', 'fetch']);
        showButtons(repo_key, ['checkout', 'checkout_pull']);
    }
}

function showButtons(repo_key, buttons)
{
    for(var i = 0; i < buttons.length; i++) {
        btn_key = '#' + getButtonKey(repo_key, buttons[i]);
        $(btn_key).show();
    }
}

function hideButtons(repo_key, buttons)
{
    for(var i = 0; i < buttons.length; i++) {
        btn_key = '#' + getButtonKey(repo_key, buttons[i]);
        $(btn_key).hide();
    }
}

function getButtonKey(repo_key, name) {
    return getKey('btn', repo_key, name);
}

function getBranchSelectionKey(repo_key)
{
    return getKey('opt-branch', repo_key);
}

function getCurrentBranchKey(repo_key)
{
    return getKey('crr-branch', repo_key);
}

function getCurrentCommitKey(repo_key)
{
    return getKey('crr-commit', repo_key);
}

function getKey(type, repo_key, name) {
    var key = type + '__' + repo_key;
    if (name) {
        key += '__' + name
    }
    return key;
}