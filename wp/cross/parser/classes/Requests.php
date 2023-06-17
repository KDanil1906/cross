<?php

namespace classes;

require_once get_template_directory() . '/parser/classes/GetProxy.php';

use classes\GetProxy;

class Requests {

	private $test_url = 'https://lzpro.ru/';
	private $ip;
	private $port;


	public function __construct( $proxy = false ) {

		if ( $proxy ) {
			$this->findProxy();
		}
	}

	/**
	 * @param string $url
	 * Принимает ссылку
	 * Возвращает содержимое страницы
	 *
	 * @return bool|string
	 */
	public function request( string $url, $pr_request = false ) {

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_HTTPGET, true );
		$user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/' . rand( 70, 90 ) . '.0.' . rand( 1000, 9999 ) . '.' . rand( 100, 999 ) . ' Safari/537.36';
		curl_setopt( $ch, CURLOPT_USERAGENT, $user_agent );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

		# Если запрос с proxy
		if ( $pr_request ) {
			curl_setopt( $ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP );
			curl_setopt( $ch, CURLOPT_PROXY, $this->ip );
			curl_setopt( $ch, CURLOPT_PROXYPORT, $this->port );
		}

		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 3 );

		$response = curl_exec( $ch );

		$http_status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

		# Если прокси не работает

		if ( $pr_request && $http_status !== 200 ) {
			$this->test_url = $url;
			$this->findProxy();

			$response = $this->request( $url, true );
		}
		curl_close( $ch );

		return $response;
	}

	/**
	 * @return void
	 * Ищет рабочие прокси
	 */
	private function findProxy() {

		$proxy = new GetProxy( $this->test_url );

		while ( true ) {

			$pr_data = $proxy->getWorkingProxy();

			if ( $pr_data ) {
				$this->ip   = $pr_data['ip'];
				$this->port = $pr_data['port'];
				break;
			}
			sleep( 5 );
		}
	}
}