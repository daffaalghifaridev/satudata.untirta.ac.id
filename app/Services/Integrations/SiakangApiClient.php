<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\PendingRequest;

abstract class SiakangApiClient extends AbstractApiClient
{
    protected function buildRequest(array $config): PendingRequest
    {
        $token = (string) ($config['token'] ?? '');

        if (trim($token) === '') {
            throw new \RuntimeException('Bearer token SIAKANG belum dikonfigurasi.');
        }

        return parent::buildRequest($config);
    }
}
