<?php

namespace classes;

class HandlingStatusParsedData {
	private string $file;


	public function __construct( string $file ) {
		$this->file = $file;
	}

	/**
	 * @param $link
	 * Проверяет наличие товара в массиве
	 *
	 * @return bool
	 */
	public function checkProductInData( $link ): bool {
		$products = $this->getArrayFromJson( $this->file );
		if ( $products ) {
			if ( key_exists( $link, $products ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param $link
	 * Возвращает статус товара в массиве
	 *
	 * @return false|mixed
	 */
	public function getProductStatus( $link ) {
		$products = $this->getArrayFromJson( $this->file );
		if ( $products ) {
			if ( key_exists( $link, $products ) ) {
				return $products[ $link ]['status'];
			}
		}

		return false;
	}

	/**
	 * @param $file_name
	 *
	 * @return mixed
	 * Возвращает ассоциативный массив из json
	 */
	public function getArrayFromJson( $file_name ) {
		if ( file_exists( $file_name ) ) {
			return json_decode( file_get_contents( $file_name ), true );
		}

		return null;
	}

}
