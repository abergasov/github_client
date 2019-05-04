<?php

namespace Github;

use Throwable;

class GithubException extends \Exception {

    private $cmdOutput = null;

    public function __construct($output, $message = "", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);

        $this->cmdOutput = $output;
    }

    public function getCmdOutput () {
        return $this->cmdOutput;
    }
}