<?php

namespace Github;

use RuntimeException;

class GithubClient {

    const APIURL = 'https://api.github.com/graphql';
    private $lastResponseHeaders = [];
    private $token = '';
    private $userName = '';

    /**
     * GithubClient constructor.
     * @param string $token see readme.md how create token
     * @param string $userName name of your github acc
     */
    public function __construct(string $token, string $userName) {
        $this->token = $token;
        $this->userName = $userName;
    }

    public function  getAccName () {
        $data = $this->gitRequest(['query' => 'query{viewer{login}}']);
        return @$data['data']['viewer']['login'];
    }

    /**
     * Check repo exist on acc, repoId if exist, false if not found. If passed $commitHistory as true, array will returned
     * @param string $repoName
     * @param bool $commitHistory need select commit history
     * @param integer $historyLength how mush last commits select
     * @throws RuntimeException if something wrong with api with http codes
     * @return bool|string|array
     */
    public function searchRepo (string $repoName, bool $commitHistory = false, int $historyLength = 5) {
        $queryAdd = '';
        if ($commitHistory) {
            $queryAdd = "ref(qualifiedName:\"master\"){target{...on Commit{history(first:$historyLength){pageInfo{hasNextPage}edges{node{message,pushedDate,oid}}}}}}";
        }
        $data = $this->gitRequest([
            'query' => 'query{viewer{repository(name:' . json_encode($repoName) . '){id,' . $queryAdd .'}}}'
        ]);
        $repoId = @$data['data']['viewer']['repository']['id'];
        if ($repoId === null) {
            return false;
        } elseif (!$commitHistory) {
            return $repoId;
        }
        $commitsList = @$data['data']['viewer']['repository']['ref']['target']['history']['edges'];
        if (is_array($commitsList)) {
            array_walk($commitsList, function (&$item) {
                $item = $item['node'];
            });
            return ['id' => $repoId, 'commits' => $commitsList];
        } else {
            return ['id' => $repoId, 'commits' => []];
        }
    }

    /**
     * Create repo.
     * @param string $name
     * @param bool $private
     * @param string $description
     * @param bool $hasIssues
     * @param bool $hasProjects
     * @param bool $hasWiki
     * @throws RuntimeException if something wrong with api with http codes
     * @return bool true if successfully created
     */
    public function createRepo (string $name, bool $private = true, string $description = '',  bool $hasIssues = false, bool $hasProjects = false, bool $hasWiki = false) : bool {
        $query = [
            'name' => $name,
            'description' => $description,
            'homepage' => 'https://github.com',
            'private' => $private,
            'has_issues' => $hasIssues,
            'has_projects' => $hasProjects,
            'has_wiki' => $hasWiki
        ];
        $res = $this->gitRequest($query, 'https://api.github.com/user/repos');
        return isset($res['id']) && $res['id'] > 0;
    }

    /**
     * Remove repo from disc
     * @param $path
     */
    public function removeRepoFromDisc ($path) {
        system("rm -rf ".escapeshellarg($path));
    }

    /**
     * Remove repo from git acc
     * @param string $repoName
     * @return bool true if removed
     */
    public function deleteRepoFromGitAcc (string $repoName) : bool {
        $res = $this->gitRequest(
            [],
            'https://api.github.com/repos/' . $this->userName . '/' . $repoName,
            'DELETE'
        );
        return $res === 204;
    }

    /**
     * Clone git repo via cmd
     * @param string $path path 2 dir with where should clone repo
     * @param string $repoName
     * @param bool $overwrite if true will remove dir and re-clone repo
     * @throws RuntimeException if git repo already exist
     * @return bool
     */
    public function cloneRepo (string $path, string $repoName, bool $overwrite = false) : bool {
        if (!is_dir($path)) {
            mkdir($path);
        } else {
            if (is_dir($path . $repoName)) {
                if (!$overwrite) {
                    throw new RuntimeException('Repo already exist', 409);
                } else {
                    $this->removeRepoFromDisc($path . $repoName);
                }
            }
        }

        $cmd = 'cd ' . $path . ' && git clone https://' . $this->token . ':x-oauth-basic@github.com/' . $this->userName .
            '/' . $repoName . '.git';

        $this->getCommandOutput($cmd);
        return is_dir($path . $repoName . '/.git');
    }

    /**
     * Get parsered git log data
     * @param string $path path 2 repo
     * @param int $deep
     * @throws RuntimeException if git repo already exist or error while log acess
     * @return array
     */
    public function getCommitHistoryFromLocalGit (string $path, int $deep = 20) : array {
        if (!is_dir($path)) {
            throw new \RuntimeException('Local repository not found at ' . $path, 404);
        }
        $cmd = 'cd ' . $path . ' && git log --pretty=format:"%h | %ar | %s" -' . $deep;
        $output = $this->getCommandOutput($cmd);
        if (is_array($output)) {
            $result = [];
            foreach ($output as $row) {
                $tmp = explode(' | ', $row);
                $result[$tmp[0]] = [
                    'date' => $tmp[1], 'comment' => $tmp[2]
                ];
            }
            return $result;
        } else {
            throw new \RuntimeException('Error get git log, command: ' . $cmd, 500);
        }
    }

    /**
     * Get files changed in revision
     * @param string $path
     * @param string $commitHash
     * @return array
     */
    public function getCommitChanges (string $path, string $commitHash) : array {
        if (!is_dir($path)) {
            throw new RuntimeException('Repository not found', 404);
        }
        $cmd = 'cd ' . $path . ' && git diff-tree --no-commit-id --name-only -r 111' . $commitHash;
        $output = $this->getCommandOutput($cmd);
        return $output;
    }

    /**
     * Commit in repo
     * @param $repoPath
     * @param $commitMessage
     * @throws RuntimeException is some troubles in commit
     * @return bool true if commit ok
     */
    public function commitRepo ($repoPath, $commitMessage) : bool {
        if (!is_dir($repoPath)) {
            throw new RuntimeException('Repository not found', 404);
        }
        $cmd = 'cd ' . $repoPath . ' && git add . && git commit -m "' .$commitMessage . '" && git push origin master';
        $output = $this->getCommandOutput($cmd);
        $output = implode('', $output);
        if (preg_match('/fatal/i', $output) === 1) {
            throw new RuntimeException('Error in repo commit');
        } else {
            return true;
        }
    }

    private function getCommandOutput ($cmd) {
        $output = [];
        $exitCode = NULL;
        exec("$cmd", $output, $exitCode);
        if($exitCode !== 0 || !is_array($output))  {
            throw new RuntimeException("Command $cmd failed.", 400);
        }
        return $output;
    }

    private function gitRequest (array $query, string $url = null, string $method = 'POST') {

        $ch = curl_init($url ? $url : self::APIURL);

        if (count($query) > 0) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($query));
        }
        curl_setopt($ch, CURLOPT_SSLVERSION, 6);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: bearer ' . $this->token,
            'User-Agent: ' . $this->userName,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno) {
            throw new RuntimeException($error . ', code' . $errno);
        }
        list($messageHeaders, $messageBody) = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);
        $this->lastResponseHeaders = $this->curlParseHeaders($messageHeaders);

        $responseCode = (int) $this->lastResponseHeaders['http_status_code'];
        if ($responseCode < 200 || $responseCode > 299) {
            throw new RuntimeException($messageBody, $this->lastResponseHeaders['http_status_code']);
        }
        return $messageBody !== '' ? json_decode($messageBody, true) : $responseCode;
    }

    private function curlParseHeaders($message_headers) {
        $headerLines = preg_split("/\r\n|\n|\r/", $message_headers);
        $headers = [];
        list(, $headers['http_status_code'], $headers['http_status_message']) = explode(' ', trim(array_shift($headerLines)), 3);
        foreach ($headerLines as $headerLine) {
            list($name, $value) = explode(':', $headerLine, 2);
            $name = strtolower($name);
            $headers[$name] = trim($value);
        }
        return $headers;
    }
}