<?php

namespace classes;

require_once get_template_directory() . '/parser/classes/GetProxy.php';

use classes\GetProxy;

class Requests {

	private $test_url = 'https://lzpro.ru/';
	private $ip;
	private $port;
	private $proxy;


	public function __construct( $proxy = false ) {

		if ( $proxy ) {
			$this->proxy = new GetProxy();
			$this->findProxy();
		}

		debug(
			array(
				'start' => true,
			)
		);

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
			debug(
				array(
					'url'  => $url,
					'ip'   => $this->ip,
					'port' => $this->port,
				)
			);

			curl_setopt( $ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP );
			curl_setopt( $ch, CURLOPT_PROXY, $this->ip );
			curl_setopt( $ch, CURLOPT_PROXYPORT, $this->port );
		}

		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5 );

		$response = curl_exec( $ch );

		$http_status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

		# Если прокси не работает

		if ( $http_status !== 200 ) {
			$this->findProxy();
			debug(
				array(
					'$http_status' => $http_status,
					'new_ip'       => $this->ip,
					'new_port'     => $this->port,
				)
			);

			$response = $this->request( $url, true );
		}

		curl_close( $ch );

//		sleep( 1 );
		return $response;
	}

	/**
	 * @return void
	 * Ищет рабочие прокси
	 */
	private function findProxy() {
		debug(
			array(
				'while' => true,
			)
		);
		while ( true ) {
			$pr_data = $this->proxy->getWorkingProxy();

			if ( $pr_data ) {
				$this->ip   = $pr_data['ip'];
				$this->port = $pr_data['port'];
				break;
			}

			debug(
				array(
					'while-loop' => true,
				)
			);

			sleep( 30 );
		}
	}

	/**
	 * Получает id и port
	 * Возвращает статус ответа
	 */
	public function checkProxy( $proxy_address, $proxy_port ) {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $this->test_url );
		curl_setopt( $ch, CURLOPT_HTTPGET, true );
		$user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/' . rand( 70, 90 ) . '.0.' . rand( 1000, 9999 ) . '.' . rand( 100, 999 ) . ' Safari/537.36';
		curl_setopt( $ch, CURLOPT_USERAGENT, $user_agent );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

		curl_setopt( $ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP );
		curl_setopt( $ch, CURLOPT_PROXY, $proxy_address );
		curl_setopt( $ch, CURLOPT_PROXYPORT, $proxy_port );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5 ); // Устанавливаем время ожидания в 5 секунд

		$response = curl_exec( $ch );
		curl_close( $ch );

		if ( $response === false ) {
			return false;
		} else {
			return curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		}
	}


}