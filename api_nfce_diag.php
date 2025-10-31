<?php
header('Content-Type: application/json; charset=utf-8');

function jexit($data){ echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT); exit; }

function loadCfg(){
    $cfgFile = dirname(__DIR__) . '/_private/config/sefaz.php';
    if (!is_file($cfgFile)) return [ 'error' => 'config_not_found', 'path' => $cfgFile ];
    $cfg = @include $cfgFile;
    if (!is_array($cfg)) return [ 'error' => 'config_invalid', 'path' => $cfgFile ];
    return $cfg;
}

function extractCnpjFromX509(string $certPem): ?string {
    $x = @openssl_x509_parse($certPem);
    if (!$x) return null;
    // tenta serialNumber e subject
    $cands = [];
    if (!empty($x['subject']['serialNumber'])) $cands[] = $x['subject']['serialNumber'];
    if (!empty($x['subject']['OID.2.16.76.1.3.3'])) $cands[] = $x['subject']['OID.2.16.76.1.3.3'];
    if (!empty($x['subject']['OID.2.16.76.1.3.1'])) $cands[] = $x['subject']['OID.2.16.76.1.3.1'];
    if (!empty($x['subject']['CN'])) $cands[] = $x['subject']['CN'];
    $dump = print_r($x, true);
    $cands[] = $dump;
    foreach ($cands as $s) {
        if (!is_string($s)) continue;
        if (preg_match('/\b(\d{14})\b/', $s, $m)) return $m[1];
        if (preg_match('/:(\d{14})\b/', $s, $m)) return $m[1];
    }
    return null;
}

function readPfxInfo(string $pfxPath, string $pfxPass): array {
    if (!is_file($pfxPath)) return [ 'exists' => false, 'path' => $pfxPath ];
    $raw = @file_get_contents($pfxPath);
    if ($raw === false) return [ 'exists' => true, 'readable' => false, 'path' => $pfxPath ];
    $certs = [];
    $ok = @openssl_pkcs12_read($raw, $certs, $pfxPass);
    if (!$ok) return [ 'exists' => true, 'readable' => true, 'pkcs12_ok' => false, 'path' => $pfxPath ];
    $cnpj = extractCnpjFromX509($certs['cert'] ?? '');
    $parsed = @openssl_x509_parse($certs['cert'] ?? '');
    return [
        'exists' => true,
        'readable' => true,
        'pkcs12_ok' => true,
        'subject' => $parsed['subject'] ?? null,
        'validFrom' => isset($parsed['validFrom_time_t']) ? date('Y-m-d H:i:s', $parsed['validFrom_time_t']) : null,
        'validTo' => isset($parsed['validTo_time_t']) ? date('Y-m-d H:i:s', $parsed['validTo_time_t']) : null,
        'cnpj_from_cert' => $cnpj
    ];
}

function curlHead(string $url, int $timeout = 15): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_NOBODY => false
    ]);
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_error($ch);
    curl_close($ch);
    return [ 'status' => (int)($info['http_code'] ?? 0), 'content_type' => $info['content_type'] ?? null, 'error' => $err ?: null, 'url' => $info['url'] ?? $url, 'snippet' => substr((string)$body, 0, 200) ];
}

try {
    $cfg = loadCfg();
    $pfxPath = $cfg['path'] ?? '';
    $pfxPass = $cfg['pass'] ?? '';
    $diag = [
        'cfg' => [
            'uf' => $cfg['uf'] ?? null,
            'ambiente' => $cfg['ambiente'] ?? null,
            'cnpj_config' => $cfg['cnpj'] ?? null,
            'pfx_path' => $pfxPath,
            'ca_bundle' => $cfg['ca_bundle'] ?? null
        ],
        'php' => [
            'version' => PHP_VERSION,
            'extensions' => [ 'curl' => extension_loaded('curl'), 'openssl' => extension_loaded('openssl') ]
        ],
        'cert' => readPfxInfo($pfxPath, $pfxPass)
    ];

    $endpoints = [
        'svrs_consulta_wsdl' => 'https://nfce.svrs.rs.gov.br/ws/NfeConsultaProtocolo/NfeConsultaProtocolo4.asmx?wsdl',
        'svrs_status_wsdl' => 'https://nfce.svrs.rs.gov.br/ws/NfeStatusServico4/NfeStatusServico4.asmx?wsdl',
        'rr_consulta_wsdl' => 'https://www.sefaz.rr.gov.br/nfce/ws/NfeConsultaProtocolo4/NfeConsultaProtocolo4.asmx?wsdl',
        'rr_services_consulta_wsdl' => 'https://www.sefaz.rr.gov.br/nfce/services/NfeConsultaProtocolo4?wsdl',
        'dist_df_e_wsdl' => 'https://www.nfe.fazenda.gov.br/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx?wsdl'
    ];
    $diag['wsdl_check'] = [];
    foreach ($endpoints as $k => $u) {
        $diag['wsdl_check'][$k] = curlHead($u);
    }

    jexit($diag);
} catch (Throwable $e) {
    jexit([ 'error' => $e->getMessage() ]);
}


