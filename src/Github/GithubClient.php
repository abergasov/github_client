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
     * Check repo exist on acc, repoId if exist, false if not found
     * @param string $repoName
     * @param bool $commitHistory need select commit history
     * @param integer $historyLength how mush last commits select
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

    public function createRepo () {

    }

    public function cloneRepo ($path) {

    }

    public function commitRepo () {

    }

    private function gitRequest (array $query) {

        $ch = curl_init(self::APIURL);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($query));
        curl_setopt($ch, CURLOPT_SSLVERSION, 6);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
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

        if ($this->lastResponseHeaders['http_status_code'] !== '200') {
            throw new RuntimeException($messageBody, $this->lastResponseHeaders['http_status_code']);
        }
        return json_decode($messageBody, true);
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