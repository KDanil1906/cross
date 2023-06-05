<?php

namespace classes;

require_once get_template_directory() . '/parser/classes/GetProxy.php';

//напирается на ссылку в которой застяет в цикле
//Получает IP проверяет его на ссылке доменной, она дает положительный результат и оставляет этот ip
//Этот ip не подходит для обработки ссылки и снова попадает на логику в рекккурсии
//Нужно передавть для проверки именну ту ссылку, на котороый спотыкается

//Requests вызывает GetProxy а тот вызывает Requests и зациклились

use classes\GetProxy;

class Requests {

	private $test_url = 'https://lzpro.ru/';
	private $ip;
	private $port;
	public static $counter = 0;
	public static $producNum = 0;
	public static $totalTime = 0;

	public function __construct( $proxy = false ) {
		debug( array( 'Requests' => 'NOPR', 'NUM' => self::$counter ) );

		if ( $proxy ) {
			debug( array( 'Requests' => 'PD', 'NUM' => self::$counter ) );
			$this->findProxy();
		}

		self::$counter ++;
	}

	/**
	 * @param string $url
	 * Принимает ссылку
	 * Возвращает содержимое страницы
	 *
	 * @return bool|string
	 */
	public function request( string $url, $pr_request = false ) {
		$start = microtime( true );

//		debug( array( 'request' => $pr_request, ) );

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_HTTPGET, true );
		$user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/' . rand( 70, 90 ) . '.0.' . rand( 1000, 9999 ) . '.' . rand( 100, 999 ) . ' Safari/537.36';
		curl_setopt( $ch, CURLOPT_USERAGENT, $user_agent );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

		# Если запрос с proxy
		if ( $pr_request ) {


			self::$producNum ++;
//			debug_counter(
//				array( 'Links' => self::$producNum )
//			);
//
//			debug(
//				array(
//					'url'  => $url,
//					'ip'   => $this->ip,
//					'port' => $this->port,
//				)
//			);
//
//			debug_clean(
//				array(
//					'url'  => $url,
//					'ip'   => $this->ip,
//					'port' => $this->port,
//				)
//			);

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
//			debug(
//				array(
//					'$http_status' => $http_status,
//					'new_ip'       => $this->ip,
//					'new_port'     => $this->port,
//				)
//			);

			$response = $this->request( $url, true );
		}
		curl_close( $ch );

		$end = microtime( true );

		$executionTime   = $end - $start;
		self::$totalTime += $executionTime;

		debug_time(
			array(
				'Time'   => $executionTime,
				'Medium' => self::$totalTime / self::$producNum,
				'url'    => $url,
			)
		);

		return $response;
	}

	/**
	 * @return void
	 * Ищет рабочие прокси
	 */
	private function findProxy() {
		debug( array( 'findProxy' => 'start', ) );

		$proxy = new GetProxy( $this->test_url );

		while ( true ) {
			debug( array( 'findProxy' => 'while', ) );

			$pr_data = $proxy->getWorkingProxy();

			if ( $pr_data ) {
				$this->ip   = $pr_data['ip'];
				$this->port = $pr_data['port'];
				break;
			}
			debug( array( 'findProxy' => 'loop', ) );
			sleep( 30 );
		}
	}
}