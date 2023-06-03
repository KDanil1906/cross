<?php

namespace classes;

class HandlingStatusParsedData {
	private $products_data;
	private $product_list;


	public function __construct() {
		$file_name           = get_template_directory() . '/parser/' . 'data.json';
		$this->products_data = json_decode( $this->get_the_data_from_json( $file_name ), true );
		$this->product_list  = $this->get_clear_products( $this->products_data );
	}

	public function set_product_status_posted( $status, $product_link ) {
//        получить и изменить json данные
	}

	/**
	 * @param $product_link
	 *
	 * @return bool
	 * Проверяю существует ли товар в базе
	 */
	public function check_product_in_file( $product_link ) {
		return array_key_exists( $this->product_list, $product_link );
	}

	/**
	 * @return array
	 * Преобразует массив товаров к удобному для обработки виду
	 */
	public function get_clear_products( $product_data ) {
		$product_list = array();

		if ( $product_data ) {
			foreach ( $product_data as $category ) {
				foreach ( $category as $nested_category ) {
					foreach ( $nested_category as $key => $products ) {
						$product_list[ $key ] = $products;
					}
				}
			}
		}


		return $product_list;
	}

	/**
	 * @param $filename
	 *
	 * @return false|string
	 * Возвращает данные
	 */
	public function get_the_data_from_json( $filename ) {
		return file_get_contents( $filename );
	}

	/**
	 * @return mixed
	 */
	public function getProductList() {
		return $this->product_list;
	}

	/**
	 * @return mixed
	 */
	public function getProductsData() {
		return $this->products_data;
	}

}
