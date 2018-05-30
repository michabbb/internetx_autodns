<?php

namespace macropage_sdk\internetx_autodns;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use SimpleXMLElement;
use Verdant\Array2XML;
use Verdant\XML2Array;

class autodns {

	CONST ENDPOINT = 'https://gateway.autodns.com';

	/** @var \GuzzleHttp\Client */
	private $client;
	private $config;

	public function __construct($config) {
		$this->client = new Client();
		$this->config = $config;
	}

	/**
	 * Insert SimpleXMLElement into SimpleXMLElement
	 *
	 * @param SimpleXMLElement $parent
	 * @param SimpleXMLElement $child
	 * @param bool             $before
	 *
	 * @return bool SimpleXMLElement added
	 */
	private static function simplexml_import_simplexml(SimpleXMLElement $parent, SimpleXMLElement $child, $before = false) {
		// check if there is something to add
		if ($child[0] === null) {
			return true;
		}

		// if it is a list of SimpleXMLElements default to the first one
		$child = $child[0];

		// insert attribute
		if ($child->xpath('.') !== [$child]) {
			$parent[$child->getName()] = (string)$child;

			return true;
		}

		$xml = $child->asXML();

		// remove the XML declaration on document elements
		if ($child->xpath('/*') === [$child]) {
			$pos = strpos($xml, "\n");
			$xml = substr($xml, $pos + 1);
		}

		return self::simplexml_import_xml($parent, $xml, $before);
	}

	/**
	 * Insert XML into a SimpleXMLElement
	 *
	 * @param SimpleXMLElement $parent
	 * @param string           $xml
	 * @param bool             $before
	 *
	 * @return bool XML string added
	 */
	private static function simplexml_import_xml(SimpleXMLElement $parent, $xml, $before = false) {
		$xml = (string)$xml;

		// check if there is something to add
		if ($nodata = !strlen($xml) or $parent[0] == null) {
			return $nodata;
		}

		// add the XML
		$node     = dom_import_simplexml($parent);
		$fragment = $node->ownerDocument->createDocumentFragment();
		$fragment->appendXML($xml);

		if ($before) {
			return (bool)$node->parentNode->insertBefore($fragment, $node);
		}

		return (bool)$node->appendChild($fragment);
	}

	/**
	 * @param $zone
	 * @param $rr_name_existing
	 * @param $rr_type_existing
	 * @param $rr_ttl_new
	 * @param $rr_pref_new
	 * @param $rr_value_new
	 *
	 * @return array
	 */
	public function replaceOrAddZoneRecordRR($zone, $rr_name_existing, $rr_type_existing, $rr_ttl_new, $rr_pref_new, $rr_value_new) {

		$zoneInfo = $this->getZone($zone);

		if ($zoneInfo['state'] && self::get_array_value(['body_parsed', 'response', 'result', 'status', 'code'], $zoneInfo) === 'S0205') {

			$zone = $zoneInfo['body_parsed']['response']['result']['data']['zone'];
			unset($zone['created'], $zone['owner'], $zone['changed'], $zone['updated_by']);

			$found = false;
			foreach ($zone['rr'] as $i => $r) {
				if ($r['name'] === $rr_name_existing && $r['type'] === $rr_type_existing) {
					$found = true;
					if ($rr_ttl_new) {
						$zone['rr'][$i]['ttl'] = $rr_ttl_new;
					}
					if ($rr_pref_new) {
						$zone['rr'][$i]['pref'] = $rr_pref_new;
					}
					if ($rr_value_new) {
						$zone['rr'][$i]['value'] = $rr_value_new;
					}
				}
			}

			if (!$found) {
				$zone['rr'][] = [
					'name'  => $rr_name_existing,
					'type'  => $rr_type_existing,
					'ttl'   => $rr_ttl_new,
					'pref'  => $rr_pref_new,
					'value' => $rr_value_new
				];
			}

			$zoneXML = Array2XML::createXML($zone, ['rootNodeName' => 'zone']);

			$request = '<?xml version="1.0" encoding="utf-8"?>
						<request>
						<task>
						<code>0202</code>
						' . $zoneXML->saveXML($zoneXML->documentElement) . '
						</task>
						</request>';


			return $this->send_msg(self::ENDPOINT, 'POST', $request);

		}

		return $zoneInfo;
	}

	public function getZone($zone) {

		$request = '<?xml version="1.0" encoding="utf-8"?>
						<request>
						<task>
						<code>0205</code>
						<zone>
						<name>' . $zone . '</name>
						</zone>
						<key></key>
						</task>
						</request>';


		return $this->send_msg(self::ENDPOINT, 'POST', $request);
	}

	/**
	 * @param        $endpoint
	 * @param string $methode
	 * @param string $request_body
	 * @param array  $extra_headers
	 *
	 * @return array
	 */
	private function send_msg($endpoint, $methode = 'GET', $request_body = '', array $extra_headers = []) {
		/** @var ClientException $error */
		$error = null;

		$headers = [];
		if (count($extra_headers)) {
			$headers = array_merge($headers, $extra_headers);
		}

		if ($methode === 'POST') {
			$headers['Content-Type'] = 'text/xml';
		}

		$request_xml = new SimpleXMLElement($request_body);
		$auth        = $request_xml->addChild('auth');
		$auth->addChild('user', $this->config['auth']['user']);
		$auth->addChild('password', $this->config['auth']['password']);
		$auth->addChild('context', $this->config['auth']['context']);

		$request_body = $request_xml->asXML();

		try {
			$res = $this->client->request(
				$methode,
				$endpoint,
				[
					'headers' => $headers,
					'body'    => $request_body,
					'debug'   => true,
				]
			);
		} catch (BadResponseException $e) {
			$error = $e;
		} catch (GuzzleException $e) {
			$error = $e;
		}

		$body = isset($res) ? $res->getBody()->getContents() : null;
		if ($error !== null) {
			$respoonse_headers = $error->getRequest()->getHeaders();
		} elseif ($res !== null) {
			$respoonse_headers = $res->getHeaders();
		} else {
			$respoonse_headers = [];
		}
		unset($client);
		//echo "Will return....\n";


		/** @noinspection NullPointerExceptionInspection */
		$statusCode = $res !== null ? $res->getStatusCode() : $error->getResponse()->getStatusCode();

		return [
			'state'       => 200 === (int)$statusCode,
			'status_code' => (int)$statusCode,
			'body'        => $body,
			'body_parsed' => XML2Array::createArray($body),
			'error'       => [
				'raw' => $error !== null ? $error->getMessage() : false
			],
			'debug'       => [
				'headers'          => $headers,
				'methode'          => $methode,
				'endpoint'         => $endpoint,
				'uri'              => isset($error) ? $error->getRequest()->getUri() : null,
				'response_headers' => $respoonse_headers,
				'request_body'     => $error !== null ? $error->getRequest()->getBody()->getContents() : $request_body,
				'request_body_raw' => isset($request_body) ? $request_body : null
			]
		];


	}

	public static function get_array_value($key, $search) {
		$value = null;
		if (is_array($search)) {
			if (self::array_key_exists_r($key, $search)) {
				$currentHaystack = $search;
				if (is_array($key)) {
					foreach ($key as $item) {
						$currentHaystack = $currentHaystack[$item];
					}
				} else {
					$currentHaystack = $currentHaystack[$key];
				}
				$value = $currentHaystack;
			}
		}

		return $value;
	}

	public static function array_key_exists_r($key, $haystack) {
		$retValue        = true;
		$currentHaystack = $haystack;
		if (is_array($haystack)) {
			if (!is_array($key)) $key = [$key];
			foreach ($key as $needle) {
				if ($currentHaystack === null) {
					$retValue = false;
				} elseif (array_key_exists($needle, $currentHaystack)) {
					$currentHaystack = $currentHaystack[$needle];
				} else {
					$retValue = false;
				}
			}
		} else {
			$retValue = false;
		}

		return $retValue;
	}

}