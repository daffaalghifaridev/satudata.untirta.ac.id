<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

class SiakangSummaryApiClient extends SiakangApiClient
{
    private int $retryAfter = 60;

    protected function buildRequest(array $config): PendingRequest
    {
        return parent::buildRequest($config)->afterResponse(function ($response) {
            if ($response->status() === 429) {
                $value = (string) $response->header('Retry-After');
                $this->retryAfter = ctype_digit($value) ? max(1, (int) $value)
                    : ($value !== '' ? max(1, (int) strtotime($value) - time()) : 60);
            }
        });
    }

    protected function serviceName(): string
    {
        return 'siakang.summaries';
    }

    protected function config(): array
    {
        return [
            'base_url' => config('services.siakang.base_url'),
            'auth_type' => 'token',
            'token' => config('services.siakang.token'),
            'cf_clearance' => config('services.siakang.cf_clearance'),
            'timeout' => 90,
        ];
    }

    public function data(string $endpoint, array $params): array
    {
        if (!app()->runningUnitTests()) {
            usleep(max(0, (int) config('satudata.api_request_interval_ms')) * 1000);
        }
        $response = $this->get($endpoint, $params);
        if ($response->status === 429 && $this->retryAfter <= 60) {
            if (!app()->runningUnitTests()) {
                sleep($this->retryAfter);
            }
            $response = $this->get($endpoint, $params);
        }
        if (!$response->success || !is_array($response->data)) {
            throw new RuntimeException("Penarikan {$endpoint} gagal (HTTP {$response->status}).");
        }

        return $response->data;
    }

    public function pages(string $endpoint, array $params): array
    {
        return iterator_to_array($this->rows($endpoint, $params), false);
    }

    public function rows(string $endpoint, array $params, int $limit = 100): iterable
    {
        $received = 0;
        $expected = null;
        $lastPage = null;
        for ($page = 1; ; $page++) {
            $data = $this->data($endpoint, array_merge($params, ['limit' => $limit, 'page' => $page]));
            $meta = array_is_list($data) ? ($data[0] ?? []) : $data;
            if (!isset($meta['data'], $meta['total'], $meta['last_page'], $meta['current_page'])
                || !is_array($meta['data']) || (int) $meta['current_page'] !== $page) {
                throw new RuntimeException("Pagination {$endpoint} tidak valid.");
            }
            $expected ??= (int) $meta['total'];
            $lastPage ??= (int) $meta['last_page'];
            if ($expected !== (int) $meta['total'] || $lastPage !== (int) $meta['last_page']
                || $lastPage < 1 || $lastPage > 2000 || ($page < $lastPage && $meta['data'] === [])) {
                throw new RuntimeException("Pagination {$endpoint} berubah atau tidak lengkap.");
            }
            $received += count($meta['data']);
            foreach ($meta['data'] as $row) {
                yield $row;
            }
            unset($meta, $data);
            gc_collect_cycles();
            if ($page === $lastPage) {
                break;
            }
        }
        if ($received !== $expected) {
            throw new RuntimeException("Jumlah hasil {$endpoint} tidak sesuai total sumber.");
        }
    }
}
