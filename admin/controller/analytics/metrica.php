<?php

namespace Opencart\Admin\Controller\Extension\Yandex\Analytics;

class Metrica extends \Opencart\System\Engine\Controller
{
	public function index(): void
	{
		$this->load->language('extension/yandex/analytics/metrica');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/yandex/analytics/metrica');

		if ($this->model_extension_yandex_analytics_metrica->hasUpdates()) {
			$data['notify_module_version'] = $this->language->get('text_notify_module_version');
		}

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token' . '=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token' . '=' . $this->session->data['user_token'] . '&type=analytics')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/yandex/analytics/metrica', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/yandex/analytics/metrica|save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=analytics');

		$data['logo'] = $this->url->link('extension/yandex/analytics/metrica|logo', 'user_token=' . $this->session->data['user_token']);
		$data['find_ya_metrica'] =  $this->url->link('extension/yandex/analytics/metrica|find_ya_metrica', 'user_token=' . $this->session->data['user_token']);

		$data['has_settings'] = !!$this->model_setting_setting->getSetting('analytics_metrica');
		$data['analytics_metrica_status'] = $this->model_setting_setting->getValue('analytics_metrica_status');
		$data['analytics_metrica_code'] = $this->model_setting_setting->getValue('analytics_metrica_code');
		$data['analytics_metrica_codes'] = json_decode($this->model_setting_setting->getValue('analytics_metrica_codes'), true);
		$data['analytics_metrica_log'] = $this->model_setting_setting->getValue('analytics_metrica_log');
		$data['analytics_metrica_debug'] = $this->model_setting_setting->getValue('analytics_metrica_debug');

		$data['log'] = '';

		$file = DIR_LOGS . 'log_yandex_metrica.log';

		if (file_exists($file)) {
			$size = filesize($file);

			if ($size >= 3145728) {
				$suffix = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

				$i = 0;

				while (($size / 1024) > 1) {
					$size = $size / 1024;
					$i++;
				}

				$data['error_warning'] = sprintf($this->language->get('error_warning'), basename($file), round(substr($size, 0, strpos($size, '.') + 4), 2) . $suffix[$i]);
			} else {
				$data['log'] = file_get_contents($file, FILE_USE_INCLUDE_PATH, null);
			}
		}

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/yandex/analytics/metrica', $data));
	}


	public function save(): void
	{
		$this->load->language('extension/yandex/analytics/metrica');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/yandex/analytics/metrica')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		if (isset($this->request->post['analytics_metrica_codes'])) {
			foreach ($this->request->post['analytics_metrica_codes'] as $row => $metrica) {
				if (empty($metrica['code'])) {
					$json['error']['metrica-' . $row . '-code'] = $this->language->get('error_metric_code');
				}
			}
		}

		if (!$json) {
			$this->model_setting_setting->editSetting('analytics_metrica', $this->request->post);
			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function logo(): void
	{
		$this->response->addHeader('Content-Type: image/svg+xml');

		if ($this->config->get('config_admin_language') === 'ru-ru') {
			$this->response->setOutput(file_get_contents(DIR_EXTENSION . 'yandex/admin/view/image/analytics/metrica/logo-ru.svg'));
		} else {
			$this->response->setOutput(file_get_contents(DIR_EXTENSION . 'yandex/admin/view/image/analytics/metrica/logo-en.svg'));
		}
	}

	public function findMetrica()
	{
		$json = $this->model_extension_yandex_analytics_metrica->findMetrica();

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install()
	{
		$this->load->model('setting/event');

		$this->model_setting_event->addEvent('yandex_metrica_checkout', '', 'catalog/controller/checkout/success/before', 'extension/yandex/analytics/metrica|checkout');
	}

	public function uninstall()
	{
		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode('yandex_metrica_checkout');
	}
}
