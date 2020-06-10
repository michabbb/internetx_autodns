<?php /** @noinspection HtmlUnknownTag */

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
	 * @param $zone
	 * @param $rr_name_existing
	 * @param $rr_type_existing
	 * @param $rr_ttl_new
	 * @param $rr_pref_new
	 * @param $rr_value_new
	 *
	 * @return array
	 */
	public function replaceOrAddZoneRecordRR($zone, $rr_name_existing, $rr_type_existing, $rr_ttl_new, $rr_pref_new, $rr_value_new): array {

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

	public function getZone($zone): array {

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
	private function send_msg($endpoint, $methode = 'GET', $request_body = '', array $extra_headers = []): array {
		/** @var ClientException $error */
		$error = null;

		$headers = [];
		if (\count($extra_headers)) {
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
		$res          = null;

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

		/** @noinspection NullPointerExceptionInspection */
		$statusCode = $res !== null ? $res->getStatusCode() : $error->getResponse()->getStatusCode();

		return [
			'state'       => 200 === $statusCode,
			'status_code' => $statusCode,
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
				'request_body_raw' => $request_body ?? null
			]
		];


	}

	public static function get_array_value($key, $search) {
		$value = null;
		if (\is_array($search) && self::array_key_exists_r($key, $search)) {
			$currentHaystack = $search;
			if (\is_array($key)) {
				foreach ($key as $item) {
					$currentHaystack = $currentHaystack[$item];
				}
			} else {
				$currentHaystack = $currentHaystack[$key];
			}
			$value = $currentHaystack;
		}

		return $value;
	}

	public static function array_key_exists_r($key, $haystack): bool {
		$retValue        = true;
		$currentHaystack = $haystack;
		if (\is_array($haystack)) {
			/** @noinspection ArrayCastingEquivalentInspection */
			if (!\is_array($key)) {
				$key = [$key];
			}
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

	/**
	 * @param $zone
	 * @param $rr_name_existing
	 * @param $rr_type_existing
	 *
	 * @return array
	 */
	public function removeZoneRecordRR($zone, $rr_name_existing, $rr_type_existing): array {

		$zoneInfo = $this->getZone($zone);

		if ($zoneInfo['state'] && self::get_array_value(['body_parsed', 'response', 'result', 'status', 'code'], $zoneInfo) === 'S0205') {

			$zone = $zoneInfo['body_parsed']['response']['result']['data']['zone'];
			unset($zone['created'], $zone['owner'], $zone['changed'], $zone['updated_by']);

			if (array_key_exists('name',$zone['rr'])) {
				$zone['rr'] = [$zone['rr']];
			}

			$found = false;
			foreach ($zone['rr'] as $i => $r) {
				if ($r['name'] === $rr_name_existing && $r['type'] === $rr_type_existing) {
					$found = $i;
				}
			}

			if (!$found) {
				throw new \RuntimeException('unable to find zone record: ' . $rr_name_existing . 'of type ' . $rr_type_existing);
			}

			$rr_to_remove               = $zone['rr'][$found];

			$tag_default = Array2XML::createXML([
													'rr_rem' => $rr_to_remove,
												], ['rootNodeName' => 'default']);

			$tag_zone = Array2XML::createXML([
												 'name'      => $zone['name'],
												 'system_ns' => $zone['system_ns']
											 ], ['rootNodeName' => 'zone']);

			$request = '<?xml version="1.0" encoding="utf-8"?>
						<request>
						<task>
						<code>0202001</code>
						' . $tag_default->saveXML($tag_default->documentElement) . '
						' . $tag_zone->saveXML($tag_zone->documentElement) . '
						</task>
						</request>';

			return $this->send_msg(self::ENDPOINT, 'POST', $request);

		}

		return $zoneInfo;
	}

}
