<?php

use macropage_sdk\internetx_autodns\autodns;

class AutoDNSTest extends init  {

    public function test_getZone() {
        $AutoDNS = new autodns($this->config);
        $return = $AutoDNS->getZone('wreijqfhiwefhioqwefhwe.info');
        $this->assertInternalType('array', $return);
        $this->assertTrue($return['state']);
        $this->assertEquals(200,$return['status_code']);
        $this->assertNotEmpty($return['body']);
        $this->assertNotEmpty($return['body_parsed']);
		$this->assertInternalType('array', $return['body_parsed']);
		$this->assertArrayHasKey('response',$return['body_parsed']);
		$this->assertArrayHasKey('result',$return['body_parsed']['response']);
		$this->assertArrayHasKey('data',$return['body_parsed']['response']['result']);
		$this->assertArrayHasKey('zone',$return['body_parsed']['response']['result']['data']);
		$this->assertArrayHasKey('name',$return['body_parsed']['response']['result']['data']['zone']);
		$this->assertArrayHasKey('status',$return['body_parsed']['response']['result']);
		$this->assertArrayHasKey('code',$return['body_parsed']['response']['result']['status']);
		$this->assertEquals('S0205',$return['body_parsed']['response']['result']['status']['code']);
		$this->assertEquals('success',$return['body_parsed']['response']['result']['status']['type']);
		$this->assertEquals('wreijqfhiwefhioqwefhwe.info',$return['body_parsed']['response']['result']['data']['zone']['name']);
    }

    public function test_replaceZoneRecord() {
		$AutoDNS = new autodns($this->config);
		print_r($AutoDNS->replaceOrAddZoneRecordRR('wreijqfhiwefhioqwefhwe.info','_acme-challenge','TXT',null,null,'ffffffffffffffffff'));
	}

	public function test_removeZoneRecord() {
		$AutoDNS = new autodns($this->config);
		print_r($AutoDNS->removeZoneRecordRR('wreijqfhiwefhioqwefhwe.info','blabla','TXT'));
	}

}

