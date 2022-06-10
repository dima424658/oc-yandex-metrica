<?php

namespace Opencart\Catalog\Controller\Extension\Yandex\Analytics;

class Metrica extends \Opencart\System\Engine\Controller
{
	public function index($status)
	{
		$data['cms_version'] = mb_substr(VERSION, 0, 3);
		$data['yandex_metrics'] = $this->config->get('analytics_metrica_codes');
		$data['yandex_metrica_status'] = $status;

		if (!empty($data['yandex_metrica_status']) && $data['yandex_metrica_status'] === '1') {
			if (!empty($data['yandex_metrics'])) {
				foreach ($data['yandex_metrics'] as $key => $value) {
					if (!empty($value['ya_metrica_webvizor']) && $value['ya_metrica_webvizor'] == true) {
						$data['yandex_metrics'][$key]['ya_metrica_webvizor'] = 'true';
					} else {
						$data['yandex_metrics'][$key]['ya_metrica_webvizor'] = 'false';
					}
				}
			}
		}

		if (isset($this->request->get['route']) && $this->request->get['route'] == 'product/product' && $this->request->get['product_id']) {
			$data['data_layer'] = json_encode($this->getProductDataLayer($this->request->get['product_id']), JSON_UNESCAPED_UNICODE);
		}

		if (isset($this->request->get['route']) && $this->request->get['route'] == 'checkout/success') {
			$data['data_layer'] = json_encode($this->getCheckoutDataLayer(), JSON_UNESCAPED_UNICODE);
		}


		return $this->load->view('extension/yandex/analytics/metrica', $data);
	}

	public function getProductDataForYandexMetrica()
	{
		$this->load->model('catalog/category');
		$this->load->model('catalog/product');
		$this->load->model('extension/yandex/analytics/metrica');

		if (isset($this->request->post['event'])) {
			$event = $this->request->post['event'];
		} else {
			$event = 'add';
		}

		if (isset($this->request->post['quantity'])) {
			$quantity = $this->request->post['quantity'];
		} else {
			$quantity = 0;
		}

		if (isset($this->request->post['id_type'])  && $this->request->post['id_type'] == 'key') {
			$product_id = 0;
			foreach ($this->cart->getProducts() as $product)
				if ($product['cart_id'] == $this->request->post['id']) {
					$product_id = $product['product_id'];
					break;
				}
		} elseif (isset($this->request->post['id_type']) && $this->request->post['id_type'] == 'product_id') {
			$product_id = $this->request->post['id'];
		} else {
			$product_id = 0;
		}

		if ($product_id === 0) {
			$this->model_extension_yandex_analytics_metrica->writeLog('Required parameters not found (name and ID)');
		}

		$product_info = $this->model_catalog_product->getProduct($product_id);
		$product_categories = $this->model_catalog_product->getCategories($product_id);

		if (count($product_categories) > 0) {
			$category = array_pop($product_categories);
			$category_info = $this->model_catalog_category->getCategory($category['category_id']);
			if ($category_info) {
				$product_category = $category_info['name'];
			} else {
				$product_category = "";
			}
		} else {
			$product_category = "";
		}

		$json = [];

		if ($event == 'add') {
			$json = [
				'currency_code' => $this->session->data['currency'],
				'product' => [
					'id' => $product_info['product_id'],
					'name' => $product_info['name'],
					'price' => $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency'], false),
					'brand' => $product_info['manufacturer'],
					'category' => $product_category,
					'quantity' => $quantity,
				],
			];
		} elseif ($event == 'remove') {
			$json = [
				'currency_code' => $this->session->data['currency'],
				'product' => [
					'id' => $product_info['product_id'],
					'name' => $product_info['name'],
					'category' => $product_category,
					'quantity' => $quantity,
				],
			];
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function checkout(string &$route, array &$args): void
	{
		if ($route == 'checkout/success' && isset($this->session->data['order_id'])) {
			$this->session->data['metrica_order_id'] = $this->session->data['order_id'];
		}
	}

	public function getProductDataLayer(): array
	{
		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		$this->load->model('extension/yandex/analytics/metrica');

		$product_id = 0;
		$product_name = '';
		$product_price = '';
		$product_manufacturer = '';
		$product_category = '';

		if (isset($this->request->get['product_id']))
			$product_id = $this->request->get['product_id'];

		if ($product_info = $this->model_catalog_product->getProduct($product_id)) {
			$product_name = $product_info['name'];

			if ((float)$product_info['special']) {
				$product_price = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			} else {
				$product_price = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			}

			$product_manufacturer = $product_info['manufacturer'];

			$product_categories = $this->model_catalog_product->getCategories($product_info["product_id"]);
			if (!empty($product_categories)) {
				$category = array_pop($product_categories);
				if ($category_info = $this->model_catalog_category->getCategory($category['category_id'])) {
					$product_category = $category_info['name'];
				}
			}
		} else {
			$this->model_extension_yandex_analytics_metrica->writeLog('Required parameters not found (name and ID)');
		}

		$data = [
			'ecommerce' => [
				'currencyCode' => $this->session->data['currency'],
				'detail' => [
					'products' => [
						[
							'id' => $product_id,
							'name' => $product_name,
							'price' => $product_price,
							'brand' => $product_manufacturer,
							'category' => $product_category
						]
					]
				]
			]
		];

		return $data;
	}


	public function getCheckoutDataLayer(): array
	{
		if (!isset($this->session->data['metrica_order_id']))
			return [];
		else
			$order_id = $this->session->data['metrica_order_id'];

		$this->load->model('account/order');
		$this->load->model('catalog/category');
		$this->load->model('catalog/product');
		$this->load->model('extension/yandex/analytics/metrica');

		$data = [];
		$products = [];
		$order_info = $this->model_extension_yandex_analytics_metrica->getOrder($order_id);
		$order_products = $this->model_account_order->getProducts($order_id);

		foreach ($order_products as $order_product) {
			$product_info = $this->model_catalog_product->getProduct($order_product['product_id']);
			$categories_product = $this->model_catalog_product->getCategories($order_product['product_id']);
			if ($product_info) {
				$metrica_product_category = '';
				if (count($categories_product) > 0) {
					$category = array_pop($categories_product);
					$category_info = $this->model_catalog_category->getCategory($category['category_id']);
					if ($category_info) {
						$metrica_product_category = $category_info['name'];
					}
				}

				$price = $this->currency->format($this->tax->calculate($order_product['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);

				$products[] = [
					'id' => $order_product['product_id'],
					'name' => $order_product['name'],
					'price' => $price,
					'quantity' => $order_product['quantity'],
					'manufacturer' => $product_info['manufacturer'],
					'category' => $metrica_product_category,
				];
			}
		}

		$data = [
			'ecommerce' => [
				'currencyCode' => $this->session->data['currency'],
				'purchase' => [
					'actionField' => [
						'id' => $order_info['order_id'],
						'coupon' => isset($this->session->data['coupon']) ? $this->session->data['coupon'] : '',
						'revenue' =>  $this->currency->format($order_info['total'], $this->session->data['currency'])
					],
					'products' => $products
				]
			]
		];

        unset($this->session->data['metrica_order_id']);

		return $data;
	}
}
