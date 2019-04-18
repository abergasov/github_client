<?php

namespace Github;

class GithubClient {

    private $token = '';

    public function __construct($token) {
        $this->token = $token;
    }

    private function gitRequest (string $query) {

    }
}