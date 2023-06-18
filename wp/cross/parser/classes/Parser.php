<?php


//<!--НЕ НЕЗАБЫТЬ-->
//<!---->
//<!--ОТКЛЮЧИТЬ СДЕРЖИВАНИЕ ПАГИНАЦИИ-->
//<!--Убедиться что все сдерживающие факторы сняты -->


namespace classes;

require_once get_template_directory() . '/parser/classes/Requests.php';
require_once get_template_directory() . '/parser/classes/HandlingStatusParsedData.php';

use classes\Requests;
use DOMDocument;
use DOMXPath;

class Parser {
	/**
	 * @var array
	 * Start category array
	 */
	private $categories_urls;

	/**
	 * @var string
	 * Parsing target domain
	 */
	private $domain;

	/**
	 * @var array
	 * Temporary product link buffer
	 */
	private $prod_buff;

	/**
	 * @var \classes\Requests
	 */
	private $request;

	/**
	 * @var string
	 * Название таблицы товаров
	 */
	private $table_name;


	public function __construct( array $urls = array() ) {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'parse_products';


		$this->categories_urls = $urls;

		$this->request = new Requests( true );


		if ( $urls ) {
			$url          = $urls[0];
			$parts        = parse_url( $url );
			$host         = explode( ".", $parts['host'] );
			$this->domain = 'https://' . $host[0] . "." . $host[1];
		}
	}


	/**
	 * Из переданных ссылок категорий забираю подкатегории и сохраняю их в массиве.
	 */
	public function get_category_links() {
//   Получаю все вложенные категории переданные через настройки страницы парсинга
		$this->get_dom_links( $this->categories_urls, 'Category__Item--All' );
	}

	/**
	 * @return mixed
	 * Получает товары из JSON и преобразует в массив
	 */
	public function getProductFromDB( $status = null ) {
		global $wpdb;

		$table_name = $this->table_name;

		if ( $status ) {
			$query = $wpdb->prepare(
				"SELECT * FROM $table_name WHERE status = %s",
				$status
			);
		} else {
			$query = "SELECT * FROM $table_name";
		}

		$results = $wpdb->get_results( $query, 'ARRAY_A' );

		if ( $results ) {
			return $results;
		}

		return null;
	}


	/**
	 * @param $data
	 * Добавляет ссылки на товары в базу данных
	 *
	 * @return void
	 */
	public function addProdDataToDB( $data ) {
		if ( $data ) {
			global $wpdb;
			$table_name = $this->table_name;

			foreach ( $data as $prod_link => $prod_data ) {

				$data = array(
					'link' => $prod_link,
				);

				foreach ( $prod_data as $item_key => $item_val ) {
					if ( is_array( $item_val ) ) {
						$item_val = json_encode( $item_val );
					}

					$data[ $item_key ] = $item_val;
				}

				$wpdb->insert( $table_name, $data );
			}
		}
	}

	/**
	 * @param $data
	 * @param $link
	 *
	 * @return void
	 * Обновляет данные товара после посещение страницы товара
	 */
	function updateProductData( $data, $link ) {
		global $wpdb;

		$table_name = $this->table_name;

		$status = isset( $data['status'] ) ? $data['status'] : null;
		$image  = isset( $data['mage'] ) ? $data['mage'] : null;
		$sku    = isset( $data['sku'] ) ? $data['sku'] : null;
		$desk   = isset( $data['desk'] ) ? $data['desk'] : null;
		$price  = isset( $data['price'] ) ? $data['price'] : null;
		$name   = isset( $data['name'] ) ? $data['name'] : null;
		$attrs = isset( $data['attrs'] ) ? json_encode( $data['attrs'] ) : 'null';

		$query = $wpdb->prepare(
			"UPDATE $table_name SET 
		    status = %s,
		    image = %s,
		    sku = %s,
		    desk = %s,
		    price = %s,
		    name = %s,
		    attrs = %s
		    WHERE link = %s",
			$status,
			$image,
			$sku,
			$desk,
			$price,
			$name,
			$attrs,
			$link
		);

		$wpdb->query( $query );
	}


	/**
	 * @return void
	 * Проходит по собранным ссылкам товара и парсин данные товара (цена, название и тд)
	 */
	public function parseProductData() {
		$this->log( array( 'ProdData' => 'START' ) );
		$product = $this->getProductFromDB( 'add-link' );

		if ( ! $product ) {
			exit();
		}

		foreach ( $product as $prod_item ) {
			$link = $prod_item['link'];

			$product_data = $this->get_product_data( $link );

			$this->updateProductData( $product_data, $link );
		}
		$this->log( array( 'ProdData' => 'END' ) );
	}


	/**
	 * @param $prod_link
	 *
	 * @return array
	 * Принимает ссылку на товар и получает данные со страницы товара
	 */
	public function get_product_data( $prod_link ) {
		$product_data = $this->request->request( $prod_link, true );

		$data = array(
			'image' => "//img[contains(@class, 'js-product-main-image')]",
			'sku'   => "//div[contains(@class, 'Single__SKU--Code') and contains(@itemprop, 'sku')]",
			'name'  => "//h1[contains(@class, 'Single__Title--Main')]",
			'desk'  => "//div[contains(@class, 'Single__Description--Text')]/p",
			'price' => "//div[contains(@class, 'Product__Price--Current')]/span[contains(@class, 'price')]",
			'attrs' => "//div[contains(@class, 'Single__Item--Attrs')]/div[contains(@class, 'Single__Key--Attrs')]//span[contains(@class, 'Single__Text--Attr-Name-Span')] | //div[contains(@class, 'Single__Item--Attrs')]/div[contains(@class, 'Single__Value--Attrs')]",
		);

		$product_result = array();

		foreach ( $data as $data_name => $data_query ) {
			$elems = self::query_get_elems_from_html( $product_data, $data_query );

			if ( ! $elems ) {
				continue;
			}

			$attrs = array();
			foreach ( $elems as $elem ) {


				$type   = $elem->nodeName == 'img' ? 'link' : 'text';
				$result = trim( $type == 'link' ? $this->domain . $elem->getAttribute( 'data-src' ) : $elem->nodeValue );

				if ( $data_name == 'sku' ) {
					$result = explode( '-', $result );
					$result = 'CR-' . $result[1];
				}

				if ( $data_name == 'image' ) {
					if ( strstr( $result, 'no-image.svg' ) ) {
						$result = false;
					}
				}

				if ( $data_name == 'attrs' ) {
					if ( count( $attrs ) > 0 ) {
						array_push( $attrs, $result );
						$result                                     = $attrs;
						$product_result[ $data_name ][ $result[0] ] = $result[1];
						$attrs                                      = array();
						continue;
					} else {
						array_push( $attrs, $result );
						continue;
					}
				}
				$product_result[ $data_name ] = $result;
			}
		}

		$product_result['status'] = 'fill';

		return $product_result;
	}

	/**
	 * @param int $page
	 * @param string $link
	 *
	 * @return false|string
	 *
	 * Формирует ссылку пагинации и получает данные с нее
	 */

	private function get_paginate_link_html( int $page, string $link ) {
		$link = $link . '?page=' . $page;

		return $this->request->request( $link, true );
	}

	/**
	 * @param array $links
	 * @param string $linkClass
	 *
	 * @return void
	 * Получает массив ссылок категорий, извлекает подкатегории, вызывает метод получения ссылок товаров подкатегорий.
	 *
	 */
	public function get_dom_links( array $links, string $linkClass ) {
		foreach ( $links as $key => $category_link ) {
			$url       = $category_link;
			$page_html = $this->request->request( $url, true );

			if ( ! $page_html ) {
				continue;
			}

			$cat_name = $this->getCatName( $page_html );

			$this->log( array( "MAIN CAT $cat_name" => "START" ) );

			$query          = "//a[contains(@class, '" . $linkClass . "')]";
			$category_links = self::query_get_elems_from_html( $page_html, $query );


			if ( $category_links->length === 0 ) {
				$this->log( array( "MAIN CAT $cat_name" => "END continue" ) );
				continue;
			}

			foreach ( $category_links as $category ) {
				$nes_cat_link = $this->domain . $category->getAttribute( 'href' );
				$this->get_and_save_pr_link( $nes_cat_link, $cat_name );
			}

			$this->log( array( "MAIN CAT $cat_name" => "END" ) );
		}
	}

	/**
	 * @param $page_html
	 * Возвращает название категории страницы
	 *
	 * @return mixed
	 */
	private function getCatName( $page_html ) {
		$query = "//h1/text()";
		$title = self::query_get_elems_from_html( $page_html, $query )->item( 0 )->nodeValue;
		$title = trim( $title );
		$title = str_replace( 'в  Москве', '', $title );

		return trim( $title );
	}

	/**
	 * @param $cat_link
	 * @param $main_cat_name
	 *
	 * @return void
	 * Получает ссылки товаров.
	 *
	 */
	public function get_and_save_pr_link( $cat_link, $main_cat_name ) {
//		Получаю ссылку категории товаров
		$page_html    = $this->request->request( $cat_link, true );
		$nes_cat_name = $this->getCatName( $page_html );

		$this->log( array( "NES CAT $nes_cat_name" => "START" ) );

		$page_num = 1;
		while ( true ) {
			$page = $this->get_paginate_link_html( $page_num, $cat_link );

			if ( ! $page ) {
				$this->log( array( "NES CAT $nes_cat_name" => "no paginate" ) );
				break;
			}

			$query         = "//a[contains(@class, 'Product__Link--Slider')]";
			$product_links = $this->query_get_elems_from_html( $page, $query );

			foreach ( $product_links as $link ) {
//				запись в буффер
				$linkHref = $link->getAttribute( 'href' );
				$linkHref = str_replace( ' ', '%20', $linkHref );
				$linkHref = $this->domain . $linkHref;

				if ( ! $this->checkProductInDB( $linkHref ) ) {
					$this->prod_buff[ $linkHref ] = array(
						'main_cat' => $main_cat_name,
						'nes_cat'  => $nes_cat_name,
						'status'   => 'add-link'
					);
				}
			}
//			УБРАТЬ
			break;

			$page_num ++;
		}

		$this->log( array( "NES CAT $nes_cat_name" => "END" ) );

		$this->addProdDataToDB( $this->prod_buff );
		$this->prod_buff = array();
	}


	/**
	 * @param $link
	 *
	 * @return bool
	 * Проверяет есть ли таблица в базе данных
	 */
	public function checkProductInDB( $link ) {
		global $wpdb;

		$table_name = $this->table_name;
		$query      = $wpdb->prepare(
			"SELECT COUNT(*) FROM $table_name WHERE link = %s",
			$link
		);

		$result = $wpdb->get_var( $query );

		if ( $result > 0 ) {
			return true;
		}

		return false;
	}


	/**
	 * @param $page_html
	 * @param $query
	 *
	 * @return DOMNodeList|false|mixed
	 *
	 * Метод получения элементов из html по query
	 */
	public static function query_get_elems_from_html( $page_html, $query ) {
		if ( $page_html ) {

			$dom = new DOMDocument();
			libxml_use_internal_errors( true );
			$dom->loadHTML( $page_html );
			$xpath  = new DOMXPath( $dom );
			$result = $xpath->query( $query );

			return $result;
		}

		return false;
	}

	/**
	 * @param $data
	 *
	 * @return void
	 * Метод лога
	 */
	private function log( $data ) {
		$file_path = get_template_directory() . '/parser/log.json';
		file_put_contents( $file_path, json_encode( $data, JSON_UNESCAPED_UNICODE ) . PHP_EOL, FILE_APPEND );
	}

	/**
	 * @return void
	 * Вызывает методы парсинга, 2 части парсинга, первая собирает ссылки, вторая проходит по ссылкам и забирает данные
	 *
	 */
	public function parse() {
		$this->get_category_links();
		$this->parseProductData();
	}
}