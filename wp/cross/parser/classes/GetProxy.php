<?php

namespace classes;

//require_once get_template_directory() . '/parser/Parser.php';
require_once get_template_directory() . '/parser/classes/Requests.php';

use classes\Parser;
use classes\Requests;

class GetProxy {

	private $proxy_buff = array();
	private $request;
	private $url = 'https://free-proxy-list.net/';

	public function __construct() {
		$this->request = new Requests();
		$this->checkProxyFromDB();
	}


	/**
	 * @return void
	 * Получает и записывает в переменную доступные proxy с ресурса в $url
	 */
	private function getProxyList() {
		$parser = new Parser();
		$page   = $this->request->request( $this->url );

		$query = "//table[contains(@class, 'table') and contains(@class, 'table-striped') and contains(@class, 'table-bordered')]/tbody/tr";

		$html_selected = $parser->query_get_elems_from_html( $page, $query );

		foreach ( $html_selected as $tr ) {
			$td_elements = $tr->getElementsByTagName( 'td' );
			$td_count    = min( 3, $td_elements->length ); // Убедитесь, что берете не более 3-х элементов

			$data_buff = array();

			for ( $i = 0; $i < $td_count; $i ++ ) {
				$td = $td_elements[ $i ];
				// Действия с каждым первым, вторым и третьим <td> элементом
				$text = $td->textContent;
				array_push( $data_buff, $text );
			}

//			if ( in_array( $data_buff[2], $this->counties ) ) {
			$this->proxy_buff[] = array(
				'ip'      => $data_buff[0],
				'port'    => $data_buff[1],
				'country' => $data_buff[2],
			);
//			}

			$data_buff = array();
		}

		$this->checkProxyList();
	}


	/**
	 * @return void
	 * Проходит по временному массиву полученных Proxy м забирает рабочие
	 */
	public function checkProxyList() {
		if ( $this->proxy_buff ) {
			foreach ( $this->proxy_buff as $proxy ) {
				$status = $this->request->checkProxy( $proxy['ip'], $proxy['port'] );

				if ( $status === 200 ) {
					$this->writeProxyToDB( $proxy['ip'], $proxy['port'] );
				}
			}
		}
	}

	/**
	 * @return mixed
	 * Получает Proxy записанные в базу данных
	 */
	public function getProxyFromDB() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'cross_proxi'; // Замените 'имя_таблицы' на фактическое имя таблицы в WordPress
		$query      = "SELECT * FROM $table_name";

		return $wpdb->get_results( $query );
	}


	/**
	 * @param $proxy_address
	 * @param $proxy_port
	 *
	 * @return void
	 *
	 * Записывает proxy в базу данных
	 */
	public function writeProxyToDB( $proxy_address, $proxy_port ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'cross_proxi'; // Получаем имя таблицы wp_posts
		$post_data  = array(
			'proxi_id' => $proxy_address,
			'port'     => $proxy_port,
		);

		if ( ! $this->getProxyData( $proxy_address, $proxy_port ) ) {
			// Формируем SQL-запрос
			$sql = $wpdb->prepare(
				"INSERT INTO $table_name (proxi_id, port) VALUES (%s, %s)",
				$post_data['proxi_id'],
				$post_data['port'],
			);

			$wpdb->query( $sql );
		}
	}

	public function getProxyData( $ip, $port ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'cross_proxi'; // Получаем имя таблицы wp_posts

		$query = $wpdb->prepare(
			"SELECT * FROM $table_name WHERE proxi_id = %s AND port = %s",
			$ip,
			$port,
		);

		return $wpdb->get_results( $query );
	}

	/**
	 * @param $id
	 *
	 * @return void
	 * Удаляет proxy из базы данных по ID
	 */
	private function deleteProxyFromDB( $id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'cross_proxi'; // Получаем имя таблицы wp_posts

		// Формируем SQL-запрос
		$sql = $wpdb->prepare(
			"DELETE FROM $table_name WHERE id = %s",
			$id,
		);

		$wpdb->query( $sql );
	}

	/**
	 * @return void
	 * Проверяет сохраненные в базу данных proxy
	 */
	public function checkProxyFromDB() {
		$proxy = $this->getProxyFromDB();

		if ( $proxy ) {
			foreach ( $proxy as $row ) {
				$id       = $row->id;
				$proxi_id = $row->proxi_id;
				$port     = $row->port;

				$status = $this->request->checkProxy( $proxi_id, $port );

				if ( $status !== 200 ) {
					$this->deleteProxyFromDB( $id );
				}
			}
		}
	}

	public function getWorkingProxy() {
		$proxy = $this->getProxyFromDB();
		if ( ! $proxy ) {
			$this->getProxyList();
			$proxy = $this->getProxyFromDB();

			if ( ! $proxy ) {
				return false;
			}
		}

		foreach ( $proxy as $row ) {
			$proxi_id = $row->proxi_id;
			$port     = $row->port;

			$status = $this->request->checkProxy( $proxi_id, $port );

			if ( $status === 200 ) {
				return array(
					'ip'   => $proxi_id,
					'port' => $port
				);
			}
		}
	}

	public function startProxy() {
		$this->getProxyList();
	}
}