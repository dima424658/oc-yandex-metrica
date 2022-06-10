<?php

namespace Opencart\Admin\Model\Extension\Yandex\Analytics;

class Metrica extends \Opencart\System\Engine\Model
{
    public function hasUpdates(): bool
    {
        $this->load->model('setting/extension');

        if (!empty($extension = $this->model_setting_extension->getInstallByCode('yandex')) && $latestVersion = $this->getLatestVersion())
            return version_compare($extension['version'], $latestVersion) < 0;
        else
            return false;
    }

    protected function getLatestVersion(): ?string
    {
        if ($json = json_decode($this->file_get_contents_curl('https://api.github.com/repos/dima424658/oc-yandex-metrica/releases/latest'), true))
            return $json['tag_name'];
        else
            return null;
    }

    public function findMetrica(): array
    {
        $count_of_metrik = 0;
        $codes_metrik = '';
        $page_code = $this->file_get_contents_curl(HTTP_CATALOG);
        $status = str_contains($page_code, 'https://mc.yandex.ru/metrika/tag.js');

        if ($status) {
            $regexp = '/ym\((.+?), \"init\"/';
            $count_of_metrik = preg_match_all($regexp, $page_code, $matches);
            $codes_metrik = $matches[1];
        }

        return [
            'success' => $status,
            'count_of_metrik' => $count_of_metrik,
            'codes_metrik' => $codes_metrik,
        ];
    }

    protected function file_get_contents_curl(string $url): string
    {
        if ($ch = curl_init()) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_USERAGENT, 'OpenCart');

            if (($result = curl_exec($ch)) !== false)
                return $result;
        }

        return '';
    }
}
