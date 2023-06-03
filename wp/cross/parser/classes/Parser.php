<?php
//Реализовать отдельный метод добавления отдельного товара в случае если во время общего парсинга найдено новый товар (или по ссылке или по названию а возможно и по SKU)


//Реализовать метод который будет сливать временные данные класса с файлов
//возможно повесить на метод реквест получени данных с каунтером, через таймаут.
//То есть делать 100 запросов скажем в минуту

//<!--НЕ НЕЗАБЫТЬ-->
//<!---->
//<!--ВКЛЮЧИТЬ ТАЙМИНГИ ЗАПРОСОВ-->
//<!--ОТКЛЮЧИТЬ СДЕРЖИВАНИЕ ПАГИНАЦИИ-->
//<!--ВКЛЮЧИТЬ ПРОВЕРКА В МЕТОДЕ product_fill_cycle()-->
//<!--Убедиться что все сдерживающие факторы сняты -->

// Использование:
//$urls = array('https://lzpro.ru/category/otis/', 'https://lzpro.ru/category/zapchasti-dlya-eskalatorov-otis/', 'https://lzpro.ru/category/liftovye-lebedki-13vtr/');
namespace classes;

require_once get_template_directory() . '/parser/classes/Requests.php';
require_once get_template_directory() . '/parser/classes/HandlingStatusParsedData.php';

use classes\Requests;
use DOMDocument;
use DOMXPath;
use classes\HandlingStatusParsedData;

class Parser {
	private $categories_urls;
	private $parse_result;
	private $domain;

	private $request;


	public function __construct( array $urls = array() ) {
		$this->categories_urls = $urls;
		$this->parse_result    = array();

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
		$this->get_dom_links( $this->categories_urls, 'Category__Item--All', function ( $category_link, $category ) {
			$this->parse_result[ $category_link ][ $this->domain . $category->getAttribute( 'href' ) ] = array();
		} );
	}

	/**
	 * @param $file
	 *
	 * @return void
	 * Запись результатов
	 */
	private function writeToJson( $file ) {
		$json      = json_encode( $file );
		$file_name = get_template_directory() . '/parser/' . 'data.json';
		file_put_contents( $file_name, $json );
	}

	/**
	 * @return void
	 * Получает ссылки товаров
	 */
	public function get_products_link() {
//        Прохожу по основным категориям
		foreach ( $this->parse_result as $general_cat => $child_category ) {
			foreach ( $child_category as $category => $product ) {

				$page_num = 1;
				while ( true ) {
					$page = $this->get_paginate_link_html( $page_num, $category );

					if ( ! $page ) {
						break;
					}

					$query         = "//a[contains(@class, 'Product__Link--Slider')]";
					$product_links = $this->query_get_elems_from_html( $page, $query );

					foreach ( $product_links as $link ) {
//                        $this->parse_result[$category_link][$this->domain . $category->getAttribute('href')] = array();
						$this->parse_result[ $general_cat ][ $category ][ $this->domain . $link->getAttribute( 'href' ) ] = array();
					}

					break;
					$page_num ++;
				}
			}
		}
	}

	/**
	 * @return void
	 * Метод проходит по собранным ссылкам - сравнивает с базой имеющихся товаров, если товара нет - вызывает метод
	 * и получает данные товара.
	 */
	private function product_fill_cycle() {
		$product_db      = new HandlingStatusParsedData();
		$product_list_db = $product_db->getProductList();

		foreach ( $this->parse_result as $category_key => $category_value ) {
			foreach ( $category_value as $nest_cat_key => $nest_cat_value ) {
				foreach ( $nest_cat_value as $pr_link => $pr_data ) {

//                    if (array_key_exists($pr_link, $product_list_db)) continue;

					$product_data                                                     = $this->get_product_data( $pr_link );
					$this->parse_result[ $category_key ][ $nest_cat_key ][ $pr_link ] = $product_data;
				}
			}
		}
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
			$elems = $this->query_get_elems_from_html( $product_data, $data_query );

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

		$product_result['status'] = 'added';

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
	 * @param callable $callback
	 *
	 * @return false
	 *
	 * Получает массив ссылок
	 * Используется для получения ссылок подкатегорий и получения ссылок товаров
	 */
	public function get_dom_links( array $links, string $linkClass, callable $callback ) {
		foreach ( $links as $key => $category_link ) {
			$url       = $category_link;
			$page_html = $this->request->request( $url, true );

			if ( ! $page_html ) {
				continue;
			}

			$query          = "//a[contains(@class, '" . $linkClass . "')]";
			$category_links = $this->query_get_elems_from_html( $page_html, $query );

			if ( $category_links->length == 0 ) {
				continue;
			}

			foreach ( $category_links as $category ) {
				$callback( $category_link, $category );
			}
		}
	}

	/**
	 * @param $page_html
	 * @param $query
	 *
	 * @return DOMNodeList|false|mixed
	 *
	 * Метод получения элементов из html по query
	 */
	public function query_get_elems_from_html( $page_html, $query ) {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( $page_html );
		$xpath = new DOMXPath( $dom );

		return $xpath->query( $query );
	}

	/**
	 * @return void
	 * Вызывает методы парсинга
	 */
	public function parse() {
		$this->get_category_links();
		$this->get_products_link();
		$this->product_fill_cycle();

		$this->writeToJson( $this->parse_result );
	}

	/**
	 * @return array
	 * getter getParseResult;
	 */
	public function getParseResult(): array {
		return $this->parse_result;
	}
}