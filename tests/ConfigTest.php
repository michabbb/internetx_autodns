<?php

class ConfigTest extends init  {

    public function test_config() {
        $this->assertInternalType('array', $this->config);
        $this->assertArrayHasKey('auth',$this->config);
        $this->assertArrayHasKey('user',$this->config['auth']);
        $this->assertArrayHasKey('password',$this->config['auth']);
        $this->assertArrayHasKey('context',$this->config['auth']);
    }

}
 