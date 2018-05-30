<?php

class init extends \PHPUnit\Framework\TestCase {

    protected $config = [];

    public function setUp() {
        global $config;
        $this->config = $config;
    }

}