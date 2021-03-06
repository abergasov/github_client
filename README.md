A simple Object Oriented wrapper for GitHub API and git cli, written with PHP.
Uses GitHub API v3 (for create repository) & GitHub API v4.

## Requirements
- PHP >= 7.1.0
- ext-curl
- ext-json

## Install
Via Composer:
```sh
$ composer require abergasov/github_client
```
## Create token
[Github manual](https://help.github.com/en/articles/creating-a-personal-access-token-for-the-command-line)

## Usage
```php
<?php

// This file is generated by Composer
require_once __DIR__ . '/vendor/autoload.php';
try {
    $client = new GithubClient('YOUR_GITHUB_TOKEN', 'YOUR_ACC_NAME');
    //get reository id and last 20 commits
    $repoData = $client->searchRepo('YOUR_REPO_NAME', true, 20);
    
    //create new private repository
    $client->createRepo('NEW_PRIVATE_REPO_NAME', true, 'Repo description');
    //create new public repository
    $client->createRepo('NEW_PUBLIC_REPO_NAME', false, 'Repo description');
    
    //clone repository to folder, overwrite, if it exist
    $client->cloneRepo('/home/user/repository/', 'YOUR_REPO_NAME', true);
    
    //clone repository to folder, if repository not exist if directory
    $client->cloneRepo('/home/user/repository/', 'YOUR_REPO_NAME', false);
    
    //commit all changes in repo
    $client->commitRepo('PATH_TO_YOUR_REPO', 'test commit');
    
    //get last 10 commits from history
    $history = $client->getCommitHistoryFromLocalGit('PATH_TO_YOUR_REPO', 10);
    
    //get files changet in commit
    $client->getCommitChanges('PATH_TO_YOUR_REPO', 'COMMIT_HASH')
    
    //remove repo from github account
    $client->deleteRepoFromGitAcc('YOUR_REPO_NAME');
    
    //remove repo from disk
    $client->removeRepoFromDisc('YOUR_REPO_NAME');
} catch (Exception $e) {
    var_dump($e->getMessage());
    var_dump($e->getCode(););
}
```
