<?php

namespace classes;

require_once get_template_directory() . '/parser/classes/GetProxy.php';

use classes\GetProxy;

class Requests {

	private $test_url = 'https://lzpro.ru/';


	/**
	 * @param string $url
	 * Принимает ссылку
	 * Возвращает содержимое страницы
	 *
	 * @return bool|string
	 */
	public function request( string $url, $pr_request = false ) {
// инициализируем новый сеанс cURL
		$ch = curl_init();
// устанавливаем URL для запроса
		curl_setopt( $ch, CURLOPT_URL, $url );
// устанавливаем метод запроса (по умолчанию GET)
		curl_setopt( $ch, CURLOPT_HTTPGET, true );
// Генерирую рандомный user agent
		$user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/' . rand( 70, 90 ) . '.0.' . rand( 1000, 9999 ) . '.' . rand( 100, 999 ) . ' Safari/537.36';
		curl_setopt( $ch, CURLOPT_USERAGENT, $user_agent );
// устанавливаем параметр для сохранения ответа в переменной
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
// выполняем запрос и сохраняем ответ в переменную

		if ( $pr_request ) {
			$proxy = new GetProxy();
			$ip    = '';
			$port  = '';

			while ( true ) {
				$pr_data = $proxy->getWorkingProxy();

				if ( $pr_data ) {
					$ip   = $pr_data['ip'];
					$port = $pr_data['port'];
					break;
				}

				sleep( 120 );
			}

			curl_setopt( $ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP );
			curl_setopt( $ch, CURLOPT_PROXY, $ip );
			curl_setopt( $ch, CURLOPT_PROXYPORT, $port );
		}


		$response = curl_exec( $ch );

// получаем статус запроса
		$http_status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

// закрываем сеанс cURL
		curl_close( $ch );

		sleep( rand( 1, 3 ) );

		return $response;
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