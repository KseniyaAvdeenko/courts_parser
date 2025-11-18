<?php
class JsonCourtParser
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function getCourtsLinks()
    {
        echo "1. Загружаем список судов...\n";
        $courtsData = $this->fetchJson($this->config['base_url'] . '/ac/search');
        if (!$courtsData || !isset($courtsData['data'])) {
            echo "❌ Не удалось загрузить список судов\n";
            return [];
        }
        return $courtsData['data'];
    }

    public function getAllCourtsData()
    {
        $courtLinks = $this->getCourtsLinks();
        echo "2. Загружаем данные о руководителях...\n";
        $federalData = $this->fetchJson($this->config['base_url'] . '/ac/map');
        if (!$federalData) {
            echo "❌ Не удалось загрузить данные о руководителях\n";
            return [];
        }
        $flattenedData = $this->flat($federalData);
        $chiefsLookup = [];
        foreach ($flattenedData as $court) {
            if (isset($court['tag'])) {
                $chiefsLookup[$court['tag']] = $court;
            }
        }

        echo "3. Обрабатываем суды...\n";
        $result = [];
        $total = count($courtLinks);

        foreach ($courtLinks as $index => $court) {
            $number = $index + 1;
            echo "[" . $number . "/" . $total . "] " . $court['name'] . "\n";

            $enhancedCourt = $this->enhanceCourtData($court, $chiefsLookup);
            $result[] = $enhancedCourt;

            // if ($number < $total) {
            //     sleep($this->config['delay']);
            // }
        }

        return $result;
    }

    public function flat($data)
    {
        $result = [];

        foreach ($data as $item) {
            // Добавляем основной элемент
            $result[] = $item;

            // Добавляем элементы из ac1
            if (isset($item['ac1']) && is_array($item['ac1'])) {
                foreach ($item['ac1'] as $ac1Item) {
                    $result[] = $ac1Item;
                }
            }

            // Добавляем элементы из ac2
            if (isset($item['ac2']) && is_array($item['ac2'])) {
                foreach ($item['ac2'] as $ac2Item) {
                    $result[] = $ac2Item;
                }
            }
        }

        return $result;
    }

    private function enhanceCourtData($court, $chiefsLookup)
    {
        $enhanced = $court;
        $tag = $court['tag'] ?? '';


        if ($this->isValidCourtUrl($court['url'] ?? '')) {
            $regionalData = $this->fetchRegionalData($court['url'], $tag);

            if ($regionalData && isset($regionalData[$tag])) {
                $federal = $regionalData[$tag];

                $enhanced = array_merge($enhanced, $federal);
            }
        }

        if (isset($chiefsLookup[$tag])) {
            $federal = $chiefsLookup[$tag];

            if (isset($federal['chief']) && !isset($enhanced['chief'])) {
                $enhanced['chief'] = $federal['chief'];
                $enhanced["email"] = $federal["email_subscr"];
            }

            if (isset($federal['phone_help']) && !isset($enhanced['phone_help'])) {
                $enhanced['phone_help'] = $federal['phone_help'];
            }
            if (empty($enhanced['address'] ?? '') && isset($federal['address1'])) {
                $enhanced['address'] = $this->combineAddress(
                    $federal['address1'] ?? '',
                    $federal['address2'] ?? ''
                );
            }
        }
        if (!isset($enhanced['address']) || empty($enhanced['address'])) {
            $enhanced['address'] = $this->combineAddress(
                $enhanced['address1'] ?? '',
                $enhanced['address2'] ?? ''
            );
        }
        return $this->updateEnhanced($enhanced);
    }
    public function updateEnhanced($enhanced)
    {
        $props = ["name", "okrug", "tag", "url", "cityname", "updated_at", "phone_help", "phone_confidence","recipient", "recipient2", "lat", "lon", "chief", "address", "email"];
        return $this->keepOnly($enhanced, $props);
    }
    private function fetchRegionalData($courtUrl, $tag)
    {
        $regionalUrls = [
            str_replace('http://', 'https://', rtrim($courtUrl, '/')) . '/ac/map',
            str_replace('http://', 'https://', rtrim($courtUrl, '/')) . '/map',
            str_replace('http://', 'https://', rtrim($courtUrl, '/')) . '/search'
        ];

        foreach ($regionalUrls as $regionalUrl) {
            echo "   🔍 Проверяем URL: " . $regionalUrl . "\n";
            $regionalData = $this->fetchJson($regionalUrl);

            if ($regionalData) {
                $flattenedRegionalData = $this->flat($regionalData);
                $regionalLookup = [];
                foreach ($flattenedRegionalData as $regionalCourt) {
                    if (isset($regionalCourt['tag'])) {
                        $regionalLookup[$regionalCourt['tag']] = $regionalCourt;
                    }
                }

                if (!empty($regionalLookup)) {
                    echo "   ✅ Данные получены с: " . $regionalUrl . "\n";
                    return $regionalLookup;
                }
            }
        }

        echo "   ⚠️ Не удалось получить региональные данные\n";
        return null;
    }
    function keepOnly($data, $propertiesToKeep)
    {
        if (is_object($data)) {
            $result = new stdClass();
            foreach ($propertiesToKeep as $property) {
                if (property_exists($data, $property)) {
                    $result->$property = $data->$property;
                }
            }
            return $result;
        } elseif (is_array($data)) {
            $result = [];
            foreach ($propertiesToKeep as $property) {
                if (array_key_exists($property, $data)) {
                    $result[$property] = $data[$property];
                }
            }
            return $result;
        }

        return $data;
    }

    private function isValidCourtUrl($url)
    {
        if (empty($url) || $url === 'http://.arbitr.ru') {
            return false;
        }
        $parsed = parse_url($url);
        return isset($parsed['host']) && strpos($parsed['host'], 'arbitr.ru') !== false;
    }

    private function combineAddress($address1, $address2)
    {
        $parts = [];

        $addr1 = trim($address1 ?? '');
        $addr2 = trim($address2 ?? '');

        if (!empty($addr1)) {
            $parts[] = $addr1;
        }

        if (!empty($addr2)) {
            $addr2 = ltrim($addr2, ',');
            $parts[] = trim($addr2);
        }
        $result = implode(', ', $parts);
        return empty($result) ? null : $result;
    }

    private function fetchJson($url)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => $this->config['user_agent'],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json, text/javascript, */*; q=0.01'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            } else {
                echo "   ❌ Ошибка JSON: " . json_last_error_msg() . "\n";
            }
        } else if ($httpCode !== 404) {
            // Выводим предупреждение только для ошибок кроме 404
            echo "   ❌ HTTP ошибка: код " . $httpCode . "\n";
        }

        return null;
    }
}
