<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Autenticação igual às demais APIs
$user = $_SESSION['auth'] ?? null;
if (!$user) {
    $token = $_COOKIE['user_token'] ?? '';
    if ($token) {
        $pos = strrpos($token, '.');
        if ($pos !== false) {
            $payloadB64 = substr($token, 0, $pos);
            $sig = substr($token, $pos + 1);
            $payload = base64_decode($payloadB64, true);
            if ($payload !== false) {
                $arr = json_decode($payload, true);
                if (is_array($arr) && isset($arr['id'])) {
                    $secret = hash('sha256', DB_PASS . '|user_secret');
                    $expected = hash_hmac('sha256', $payload, $secret);
                    if (hash_equals($expected, $sig)) {
                        $_SESSION['auth'] = [ 'user_id' => (int)$arr['id'], 'name' => 'Participante', 'login_at' => time() ];
                        $user = $_SESSION['auth'];
                    }
                }
            }
        }
    }
}
if (!$user) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Não autenticado']); exit; }

// Utilitários
function validarChaveNFe(string $chave): bool {
    // Aceita qualquer sequência numérica de 44 dígitos
    return preg_match('/^\d{44}$/', $chave) === 1;
}

function verificarDVChaveNFe(string $chave): ?bool {
    if (!preg_match('/^\d{44}$/', $chave)) return null;
    $dvInformado = (int)substr($chave, -1);
    $numeros = substr($chave, 0, 43);
    $peso = 2; $soma = 0;
    for ($i = strlen($numeros) - 1; $i >= 0; $i--) {
        $soma += ((int)$numeros[$i]) * $peso;
        $peso = ($peso == 9) ? 2 : $peso + 1;
    }
    $dv = 11 - ($soma % 11);
    if ($dv >= 10) $dv = 0;
    return $dv === $dvInformado;
}

function extrairInfoChave(string $chave): array {
    // Layout: cUF(2) AAMM(4) CNPJ(14) mod(2) serie(3) nNF(9) tpEmis(1) cNF(8) dv(1)
    $cUF = substr($chave, 0, 2);
    $ano = substr($chave, 2, 2);
    $mes = substr($chave, 4, 2);
    $cnpj = substr($chave, 6, 14);
    $mod = substr($chave, 20, 2);
    return [
        'cUF' => (int)$cUF,
        'ano' => $ano,
        'mes' => $mes,
        'cnpj' => $cnpj,
        'mod' => $mod
    ];
}

function carregarConfigCert(): array {
    $cfgPath = __DIR__ . '/../_private/config/sefaz.php';
    if (!file_exists($cfgPath)) {
        $cfgPath = dirname(__DIR__) . '/_private/config/sefaz.php';
    }
    if (file_exists($cfgPath)) {
        $cfg = require $cfgPath;
        return is_array($cfg) ? $cfg : [];
    }
    return [];
}

function criarPemTemporario(string $pfxPath, string $pfxPass): array {
    if (!file_exists($pfxPath)) {
        throw new Exception('Certificado A1 não encontrado. Envie o arquivo PFX para _private/certs/a1_posto.pfx');
    }
    $pfxContent = file_get_contents($pfxPath);
    if ($pfxContent === false) throw new Exception('Falha ao ler o PFX');
    $certs = [];
    if (!openssl_pkcs12_read($pfxContent, $certs, $pfxPass)) {
        throw new Exception('Não foi possível abrir o PFX. Verifique a senha.');
    }
    $pemData = ($certs['cert'] ?? '') . "\n" . ($certs['pkey'] ?? '');
    if (empty(trim($pemData))) throw new Exception('PFX inválido. Certificado ou chave não encontrados.');
    $pemPath = tempnam(sys_get_temp_dir(), 'a1pem_');
    if (!file_put_contents($pemPath, $pemData)) throw new Exception('Falha ao criar PEM temporário');
    @chmod($pemPath, 0600);
    return [$pemPath, $certs];
}

function extractCnpjFromCert(string $certPem): ?string {
    $parsed = @openssl_x509_parse($certPem);
    if (!$parsed || empty($parsed['subject'])) return null;
    $subject = $parsed['subject'];
    // Tentativas comuns: serialNumber (ex: CNPJ:12345678000190), ou OID 2.5.4.5
    $candidates = [];
    if (!empty($subject['serialNumber'])) $candidates[] = $subject['serialNumber'];
    if (!empty($subject['2.5.4.5'])) $candidates[] = $subject['2.5.4.5'];
    if (!empty($subject['OID.2.5.4.5'])) $candidates[] = $subject['OID.2.5.4.5'];
    foreach ($candidates as $c) {
        $onlyDigits = preg_replace('/\D+/', '', (string)$c);
        if (strlen($onlyDigits) === 14) return $onlyDigits;
    }
    return null;
}

function curlSoapRequest(string $url, string $action, string $xmlBody, string $pemPath, ?string $sslKeyPass = null, int $timeout = 30, ?string $caBundle = null, bool $insecureOnCaFail = false): string {
    $envelope = '<?xml version="1.0" encoding="utf-8"?>'
        . '<soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">'
        . '<soap12:Body>' . $xmlBody . '</soap12:Body>'
        . '</soap12:Envelope>';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $envelope,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_POSTREDIR => 7,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/soap+xml; charset=utf-8; action="' . $action . '"',
            'Accept: application/soap+xml, application/xml, text/xml, */*',
            'Expect:'
        ],
        CURLOPT_SSLCERT => $pemPath,
        CURLOPT_SSLKEY => $pemPath,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($caBundle && file_exists($caBundle)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
    }
    if ($sslKeyPass) {
        curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $sslKeyPass);
        curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $sslKeyPass);
    }

    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $sslErr = curl_errno($ch);
        // Se erro for relacionado a CA e permitirmos fallback, desabilitar verificação e tentar novamente
        if ($insecureOnCaFail && (stripos($err, 'certificate') !== false || $sslErr === 60)) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $resp = curl_exec($ch);
        }
        if ($resp === false) {
            $finalErr = curl_error($ch);
            $finalInfo = curl_getinfo($ch);
            $trace = [ 'stage' => 'soap12', 'endpoint' => $url, 'action' => $action, 'http' => $http, 'ssl_errno' => $sslErr, 'err' => $finalErr, 'info' => $finalInfo ];
            curl_close($ch);
            throw new Exception('Erro de comunicação com SEFAZ: ' . json_encode($trace));
        }
    }
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http >= 400) {
        $snippet = firstChars($resp);
        $info = curl_getinfo($ch);
        curl_close($ch);
        throw new Exception('HTTP ' . $http . ' ao consultar serviço da SEFAZ (' . $url . ') body=' . json_encode($snippet) . ' info=' . json_encode($info));
    }
    curl_close($ch);
    return $resp;
}

function curlSoapRequest11(string $url, string $action, string $xmlBody, string $pemPath, ?string $sslKeyPass = null, int $timeout = 30, ?string $caBundle = null, bool $insecureOnCaFail = false): string {
    $envelope = '<?xml version="1.0" encoding="utf-8"?>'
        . '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
        . '<soap:Body>' . $xmlBody . '</soap:Body>'
        . '</soap:Envelope>';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $envelope,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_POSTREDIR => 7,
        CURLOPT_HTTPHEADER => [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: ' . $action,
            'Accept: text/xml, application/xml, */*',
            'Expect:'
        ],
        CURLOPT_SSLCERT => $pemPath,
        CURLOPT_SSLKEY => $pemPath,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($caBundle && file_exists($caBundle)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
    }
    if ($sslKeyPass) {
        curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $sslKeyPass);
        curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $sslKeyPass);
    }

    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        $sslErr = curl_errno($ch);
        if ($insecureOnCaFail && (stripos($err, 'certificate') !== false || $sslErr === 60)) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $resp = curl_exec($ch);
        }
        if ($resp === false) {
            $finalErr = curl_error($ch);
            $finalInfo = curl_getinfo($ch);
            $trace = [ 'stage' => 'soap11', 'endpoint' => $url, 'action' => $action, 'ssl_errno' => $sslErr, 'err' => $finalErr, 'info' => $finalInfo ];
            curl_close($ch);
            throw new Exception('Erro de comunicação com SEFAZ (SOAP 1.1): ' . json_encode($trace));
        }
    }
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http >= 400) {
        $snippet = firstChars($resp);
        $info = curl_getinfo($ch);
        curl_close($ch);
        throw new Exception('HTTP ' . $http . ' ao consultar serviço da SEFAZ (SOAP 1.1) (' . $url . ') body=' . json_encode($snippet) . ' info=' . json_encode($info));
    }
    curl_close($ch);
    return $resp;
}

function xmlExtract(string $xml, string $tag): ?string {
    // Case-insensitive, permite prefixo de namespace opcional
    $pattern = '#<([a-zA-Z0-9_]+:)?' . preg_quote($tag, '#') . '[^>]*>(.*?)</([a-zA-Z0-9_]+:)?' . preg_quote($tag, '#') . '>#is';
    if (preg_match($pattern, $xml, $m)) return trim($m[2]);
    return null;
}

function firstChars(string $s, int $max = 400): string {
    $s = trim(preg_replace('/\s+/', ' ', $s));
    if (strlen($s) > $max) return substr($s, 0, $max) . '...';
    return $s;
}

function httpGet(string $url, int $timeout = 20, bool $insecure = true): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'User-Agent: Mozilla/5.0 NFCeBot'
        ]
    ]);
    if ($insecure) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [ $code, (string)$body ];
}

function httpPost(string $url, array $data, int $timeout = 20, bool $insecure = true): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'User-Agent: Mozilla/5.0 NFCeBot'
        ]
    ]);
    if ($insecure) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [ $code, (string)$body ];
}

function makeAbsoluteUrl(string $base, string $u): ?string {
    if (!$u) return null;
    if (preg_match('#^https?://#i', $u)) return $u;
    // base parts
    $p = parse_url($base);
    if (!$p || empty($p['scheme']) || empty($p['host'])) return null;
    $scheme = $p['scheme'];
    $host = $p['host'];
    $port = isset($p['port']) ? (":".$p['port']) : '';
    $prefix = $scheme. "://" . $host . $port;
    if (strpos($u, '/') === 0) return $prefix . $u;
    $path = isset($p['path']) ? preg_replace('#/[^/]*$#', '/', $p['path']) : '/';
    return $prefix . $path . $u;
}

function findFollowupUrls(string $html, string $baseUrl): array {
    $urls = [];
    // src or href
    if (preg_match_all('#(?:src|href)\s*=\s*\"([^\"]+)\"#i', $html, $m)) {
        foreach ($m[1] as $u) {
            $abs = makeAbsoluteUrl($baseUrl, $u);
            if (!$abs) continue;
            if (!preg_match('#nfce|nfc-e|NFCe|NFCE#i', $abs)) continue;
            $urls[$abs] = true;
        }
    }
    // URLs em JS
    if (preg_match_all('#\"(https?://[^\"]*NFCE[^\"]*)\"#i', $html, $m2)) {
        foreach ($m2[1] as $abs) { $urls[$abs] = true; }
    }
    return array_keys($urls);
}

function parsePublicNfceHtml(string $html): array {
    // Normaliza HTML para texto simples e facilita regex
    $orig = $html;
    $enc = mb_detect_encoding($html, ['UTF-8','ISO-8859-1','Windows-1252','ASCII'], true) ?: 'UTF-8';
    if ($enc !== 'UTF-8') {
        $html = @mb_convert_encoding($html, 'UTF-8', $enc);
    }
    $html = preg_replace('#<script[^>]*>[\s\S]*?</script>#i', ' ', $html);
    $html = preg_replace('#<style[^>]*>[\s\S]*?</style>#i', ' ', $html);
    $text = strip_tags($html);
    // normaliza espaços não separáveis e similares
    $text = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "&nbsp;"], ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);

    $emitente = null; $cnpj = null; $dhEmi = null; $vNF = null;

    // CNPJ do emitente
    if (preg_match('/CNPJ\s*[:\-]?\s*([0-9\.\/\-]{14,18})/i', $text, $m)) {
        $cnpj = preg_replace('/\D+/', '', $m[1]);
    } elseif (preg_match('/\b\d{2}\.\d{3}\.\d{3}\/\d{4}\-\d{2}\b/', $text, $m)) {
        $cnpj = preg_replace('/\D+/', '', $m[0]);
    }

    // Data/hora de emissão
    if (preg_match('/(\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}(?::\d{2})?)/', $text, $m)) {
        $dhEmi = $m[1];
    } elseif (preg_match('/Emiss[aã]o\s*:?\s*(\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}(?::\d{2})?)/i', $text, $m)) {
        $dhEmi = $m[1];
    }

    // Valor total: tenta múltiplos padrões; se vários valores, escolhe o maior (provável total)
    $candidates = [];
    // 1) padrões diretos no HTML original (alguns portais expõem JSON ou labels com id)
    if (preg_match_all('/\b"vNF"\s*:\s*"([0-9\.,]+)"/i', $orig, $mjson)) {
        $candidates = array_merge($candidates, $mjson[1]);
    }
    if (preg_match_all('/id=\"(?:vNF|lblValor(?:Total)?|valorTotal)\"[^>]*>\s*R\$\s*([0-9\.,]+)/i', $orig, $mlbl)) {
        $candidates = array_merge($candidates, $mlbl[1]);
    }
    if (preg_match_all('/name=\"vNF\"\s*value=\"([0-9\.,]+)\"/i', $orig, $mform)) {
        $candidates = array_merge($candidates, $mform[1]);
    }
    if (preg_match_all('/R\$\s*([0-9]{1,3}(?:[\.\s\x{00A0}]\d{3})*(?:,\d{2})?)/u', $orig, $mraw)) {
        $candidates = array_merge($candidates, $mraw[1]);
    }
    if (preg_match_all('/VALOR\s*(?:TOTAL|A\s*PAGAR)\s*:?\s*R\$[\s\x{00A0}]*(\d{1,3}(?:[\.\s\x{00A0}]\d{3})*(?:,\d{2})?)/iu', $text, $mm)) {
        $candidates = array_merge($candidates, $mm[1]);
    }
    // padrões sem R$ próximos a palavras Total/Pagar/Valor
    if (preg_match_all('/(?:TOTAL|A\s*PAGAR|VALOR)\s*:?\s*(\d{1,3}(?:[\.\s\x{00A0}]\d{3})*(?:,\d{2}))/iu', $text, $mx)) {
        $candidates = array_merge($candidates, $mx[1]);
    }
    if (preg_match_all('/R\$[\s\x{00A0}]*(\d{1,3}(?:[\.\s\x{00A0}]\d{3})*(?:,\d{2})?)/u', $text, $mm2)) {
        $candidates = array_merge($candidates, $mm2[1]);
    }
    // limpa e pega o maior
    $max = 0.0; $best = null;
    foreach ($candidates as $raw) {
        $norm = preg_replace('/[\s\x{00A0}]/u', '', $raw);
        // converte pt-BR para float
        $num = (float) str_replace(['.', ','], ['', '.'], $norm);
        if ($num > $max) { $max = $num; $best = $raw; }
    }
    if ($best === null) {
        // fallback final: pegar qualquer número com vírgula padrão BR (último do texto)
        if (preg_match_all('/\b\d{1,3}(?:[\.\s\x{00A0}]\d{3})*(?:,\d{2})\b/u', $text, $mall) && !empty($mall[0])) {
            $best = end($mall[0]);
        }
    }
    if ($best !== null) {
        $vNF = $best;
    }

    // Nome/razão social do emitente
    if (preg_match('/Raz[aã]o\s*Social\s*:?\s*([A-Z0-9\s\'\.\-&\,]{3,})/iu', $text, $m)) {
        $emitente = trim($m[1]);
    } elseif (preg_match('/Emitente\s*:?\s*([A-Z0-9\s\'\.\-&\,]{3,})/iu', $text, $m)) {
        $emitente = trim($m[1]);
    } elseif (preg_match('/\bAUTO\s+POSTO[^\d\n]{3,}/iu', $text, $m)) {
        $emitente = trim($m[0]);
    }

    return [
        'emitente' => $emitente ?: null,
        'cnpj_emitente' => $cnpj ?: null,
        'dhEmi' => $dhEmi ?: null,
        'vNF' => $vNF ? ('R$ ' . preg_replace('/[\s\x{00A0}]/u', '', $vNF)) : null,
        'raw_ok' => (bool)($emitente || $cnpj || $dhEmi || $vNF)
    ];
}

function consultarPublicoRR(string $chave): ?array {
    $candidates = [
        'https://portalapp.sefaz.rr.gov.br/nfce/consulta?chave=' . $chave,
        'https://www.sefaz.rr.gov.br/nfce/consulta?chave=' . $chave,
        'https://portalapp.sefaz.rr.gov.br/nfce/consulta?chNFe=' . $chave,
        'https://www.sefaz.rr.gov.br/nfce/consulta?chNFe=' . $chave,
        // Tentativas genéricas de qrcode sem CSC (alguns portais aceitam só chave)
        'https://portalapp.sefaz.rr.gov.br/nfce/qrcode?p=' . $chave,
        'https://www.sefaz.rr.gov.br/nfce/qrcode?p=' . $chave,
        // Tentativa via SVRS (alguns estados hospedam página pública no domínio SVRS)
        'https://www.sefaz.rs.gov.br/NFCE/NFCE-COM.aspx?p=' . $chave
    ];
    $last = [];
    $fallbackUrl = null;
    foreach ($candidates as $u) {
        [ $code, $html ] = httpGet($u);
        $last[] = [ 'endpoint' => $u, 'code' => $code ];
        if ($code >= 200 && $code < 400 && $html) {
            $parsed = parsePublicNfceHtml($html);
            if ($parsed['raw_ok']) {
                $parsed['success'] = true; $parsed['origem'] = 'publico';
                $parsed['url_publica'] = $u;
                $parsed['debug'] = [ 'endpoint' => $u, 'tried' => $last ];
                return $parsed;
            }
            // guarda fallback e segue tentando outras variantes (inclui CSC)
            if ($fallbackUrl === null) $fallbackUrl = $u;
            continue;
        }
        // 403/404 podem ainda conter HTML com dados
        if (($code === 403 || $code === 404) && $html) {
            $parsed = parsePublicNfceHtml($html);
            if ($parsed['raw_ok']) {
                $parsed['success'] = true; $parsed['origem'] = 'publico';
                $parsed['url_publica'] = $u;
                $parsed['debug'] = [ 'endpoint' => $u, 'tried' => $last, 'status' => $code ];
                return $parsed;
            }
            // Seguir links internos (iframe/ajax) na mesma página
            foreach (findFollowupUrls($html, $u) as $fu) {
                [ $c2, $h2 ] = httpGet($fu);
                $last[] = [ 'endpoint' => $fu, 'code' => $c2 ];
                if ($h2) {
                    $p2 = parsePublicNfceHtml($h2);
                    if ($p2['raw_ok']) {
                        $p2['success'] = true; $p2['origem'] = 'publico';
                        $p2['url_publica'] = $fu;
                        $p2['debug'] = [ 'endpoint' => $fu, 'tried' => $last, 'via' => 'followup' ];
                        return $p2;
                    }
                }
            }
        }
    }
    // Tenta via POST em /nfce/consulta (alguns portais exigem envio por formulário)
    $postCandidates = [
        'https://portalapp.sefaz.rr.gov.br/nfce/consulta',
        'https://www.sefaz.rr.gov.br/nfce/consulta'
    ];
    foreach ($postCandidates as $u) {
        [ $code, $html ] = httpPost($u, [ 'chave' => $chave ]);
        $last[] = [ 'endpoint' => $u, 'code' => $code, 'method' => 'POST' ];
        if ($code >= 200 && $code < 400 && $html) {
            $parsed = parsePublicNfceHtml($html);
            if ($parsed['raw_ok']) {
                $parsed['success'] = true; $parsed['origem'] = 'publico';
                $parsed['url_publica'] = $u;
                $parsed['debug'] = [ 'endpoint' => $u, 'tried' => $last ];
                return $parsed;
            }
            return [ 'success' => true, 'origem' => 'publico', 'url_publica' => $u, 'debug' => [ 'tried' => $last ] ];
        }
    }
    // Se ainda falhou, tenta compor URL de QRCode com CSC (se configurado)
    $cfg = carregarConfigCert();
    $cscId = $cfg['csc_id'] ?? null;
    $cscToken = $cfg['csc_token'] ?? null;
    if ($cscId && $cscToken) {
        // Tenta vários formatos de parâmetro p
        $tpAmb = (strtolower((string)($cfg['ambiente'] ?? 'producao')) === 'homologacao') ? '2' : '1';
        $variants = [];
        // Especificação NFC-e 2.00 (padrão exigido pela SVRS para muitos casos):
        // p = chNFe|2|tpAmb|cIdToken|cHashQRCode
        // cHashQRCode = SHA1( chNFe + 2 + tpAmb + cIdToken + CSC )  [concatenação sem separadores]
        $concat = $chave . '2' . $tpAmb . (string)$cscId . (string)$cscToken;
        $hashSpec = strtoupper(sha1($concat));
        $variants[] = $chave . '|2|' . $tpAmb . '|' . $cscId . '|' . $hashSpec;
        // Variante sem tpAmb (alguns portais RR/SVRS aceitam):
        $concatNoAmb = $chave . '2' . (string)$cscId . (string)$cscToken;
        $hashNoAmb = strtoupper(sha1($concatNoAmb));
        $variants[] = $chave . '|2|' . $cscId . '|' . $hashNoAmb;
        // Repetir sem zeros à esquerda do idToken
        $idTrim = ltrim((string)$cscId, '0');
        if ($idTrim !== (string)$cscId && $idTrim !== '') {
            $variants[] = $chave . '|2|' . $tpAmb . '|' . $idTrim . '|' . strtoupper(sha1($chave . '2' . $tpAmb . $idTrim . (string)$cscToken));
            $variants[] = $chave . '|2|' . $idTrim . '|' . strtoupper(sha1($chave . '2' . $idTrim . (string)$cscToken));
        }
        // Outras variantes toleradas por alguns portais
        $base = $chave . '|2|' . $cscId;
        $variants[] = $base . '|' . strtoupper(hash_hmac('sha1', $base, $cscToken)); // HMAC-SHA1
        $variants[] = $base . '|' . strtoupper(sha1($base . $cscToken));            // SHA1 simples com pipes
        $baseAmb = $chave . '|' . $tpAmb . '|' . $cscId;
        $variants[] = $baseAmb . '|' . strtoupper(hash_hmac('sha1', $baseAmb, $cscToken));
        $variants[] = $baseAmb . '|' . strtoupper(sha1($baseAmb . $cscToken));

        $qrCandidates = [];
        foreach ($variants as $p) {
            $qrCandidates[] = 'https://portalapp.sefaz.rr.gov.br/nfce/qrcode?p=' . $p;
            $qrCandidates[] = 'https://www.sefaz.rr.gov.br/nfce/qrcode?p=' . $p;
            $qrCandidates[] = 'https://www.sefaz.rs.gov.br/NFCE/NFCE-COM.aspx?p=' . $p;
            $qrCandidates[] = 'https://dfe-portal.svrs.rs.gov.br/Dfe/QrCodeNFCe?p=' . $p;
            $qrCandidates[] = 'https://dfe-portal.svrs.rs.gov.br/Dfe/qrcodeNFCe?p=' . $p;
            $qrCandidates[] = 'https://nfce.sefaz.rs.gov.br/Portal/NFCE/consulta?p=' . $p;
        }
        foreach ($qrCandidates as $u) {
            [ $code, $html ] = httpGet($u);
            $last[] = [ 'endpoint' => $u, 'code' => $code ];
            if ($code >= 200 && $code < 400 && $html) {
                $parsed = parsePublicNfceHtml($html);
                if ($parsed['raw_ok']) {
                    $parsed['success'] = true; $parsed['origem'] = 'publico';
                    $parsed['url_publica'] = $u;
                    $parsed['debug'] = [ 'endpoint' => $u, 'tried' => $last ];
                    return $parsed;
                }
                if ($fallbackUrl === null) $fallbackUrl = $u;
                continue;
            }
        }
    }
    if ($fallbackUrl !== null) {
        return [ 'success' => true, 'origem' => 'publico', 'url_publica' => $fallbackUrl, 'debug' => [ 'tried' => $last ] ];
    }
    return [ 'success' => false, 'tried' => $last ];
}

function parseDocZipXml(string $xmlString): array {
    $out = [
        'emitente' => null,
        'cnpj_emitente' => null,
        'dhEmi' => null,
        'vNF' => null,
        'itens' => []
    ];
    // Remover BOM e normalizar
    $xmlString = preg_replace('/^\xEF\xBB\xBF/', '', $xmlString);
    $sx = @simplexml_load_string($xmlString);
    if ($sx === false) return $out;
    $namespaces = $sx->getNamespaces(true);
    $nfeNs = $namespaces['nfe'] ?? 'http://www.portalfiscal.inf.br/nfe';
    $sx->registerXPathNamespace('n', $nfeNs);

    // resNFe (resumo)
    if ($sx->getName() === 'resNFe') {
        $out['emitente'] = (string)($sx->xNome ?? '');
        $out['cnpj_emitente'] = (string)($sx->CNPJ ?? '');
        $out['dhEmi'] = (string)($sx->dhEmi ?? '');
        $out['vNF'] = (string)($sx->vNF ?? '');
        return $out;
    }

    // nfeProc -> NFe -> infNFe
    $nfeNode = null;
    if ($sx->getName() === 'nfeProc') {
        $nfeNode = $sx->NFe ?? null;
    } elseif ($sx->getName() === 'NFe') {
        $nfeNode = $sx;
    }
    if ($nfeNode) {
        $nfeNode->registerXPathNamespace('n', $nfeNs);
        $emit = $nfeNode->xpath('n:infNFe/n:emit');
        if ($emit && isset($emit[0])) {
            $out['emitente'] = (string)($emit[0]->xNome ?? '');
            $out['cnpj_emitente'] = (string)($emit[0]->CNPJ ?? '');
        }
        $ide = $nfeNode->xpath('n:infNFe/n:ide');
        if ($ide && isset($ide[0])) {
            $out['dhEmi'] = (string)($ide[0]->dhEmi ?? $ide[0]->dEmi ?? '');
        }
        $tot = $nfeNode->xpath('n:infNFe/n:total/n:ICMSTot');
        if ($tot && isset($tot[0])) {
            $out['vNF'] = (string)($tot[0]->vNF ?? '');
        }
        $dets = $nfeNode->xpath('n:infNFe/n:det');
        foreach ($dets as $det) {
            $prod = $det->prod ?? null;
            if ($prod) {
                $out['itens'][] = [
                    'xProd' => (string)($prod->xProd ?? ''),
                    'qCom' => (string)($prod->qCom ?? ''),
                    'vUnCom' => (string)($prod->vUnCom ?? ''),
                    'vProd' => (string)($prod->vProd ?? '')
                ];
            }
        }
    }
    return $out;
}

function tentarDistribuicaoPorChave(string $pemPath, string $tpAmb, int $cUF, string $cnpjEmissor, string $chave): ?array {
    if (!$cnpjEmissor || !preg_match('/^\d{14}$/', $cnpjEmissor)) return null;
    $url = ($tpAmb === '2')
        ? 'https://hom.nfe.fazenda.gov.br/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx'
        : 'https://www.nfe.fazenda.gov.br/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx';
    $action = 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeDistribuicaoDFe/nfeDistDFeInteresse';

    $cfg = carregarConfigCert();
    $attemptsCuf = array_unique([91, $cUF]);
    $steps = [];
    foreach ($attemptsCuf as $cufa) {
        $dist = '<nfeDistDFeInteresse xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/NFeDistribuicaoDFe">'
              . '<distDFeInt versao="1.01" xmlns="http://www.portalfiscal.inf.br/nfe">'
              . '<tpAmb>' . htmlspecialchars($tpAmb) . '</tpAmb>'
              . '<cUFAutor>' . (int)$cufa . '</cUFAutor>'
              . '<CNPJ>' . htmlspecialchars($cnpjEmissor) . '</CNPJ>'
              . '<consChNFe>'
              . '<chNFe>' . htmlspecialchars($chave) . '</chNFe>'
              . '</consChNFe>'
              . '</distDFeInt>'
              . '</nfeDistDFeInteresse>';

        $resp = curlSoapRequest($url, $action, $dist, $pemPath, null, 30, $cfg['ca_bundle'] ?? null, (bool)($cfg['allow_insecure_on_ca_fail'] ?? false));
        $xml = xmlExtract($resp, 'retDistDFeInt');
        if (!$xml) {
            $steps[] = ['cUFAutor'=>$cufa,'result'=>'no_retDistDFeInt','snippet'=>firstChars($resp)];
            continue;
        }
        $cStat = xmlExtract($xml, 'cStat') ?: '';
        $xMotivo = xmlExtract($xml, 'xMotivo') ?: '';
        $docs = [];
        if (preg_match('#<([a-zA-Z0-9_]+:)?loteDistDFeInt#i', $xml)) {
            if (preg_match_all('#<([a-zA-Z0-9_]+:)?docZip[^>]*>(.*?)</([a-zA-Z0-9_]+:)?docZip>#is', $xml, $mm)) {
                foreach ($mm[2] as $b64) {
                    $bin = base64_decode(trim($b64));
                    if ($bin === false) continue;
                    $unzip = @gzdecode($bin);
                    if ($unzip === false) {
                        $unzip = @gzinflate(substr($bin, 10));
                    }
                    if ($unzip !== false) {
                        $docs[] = $unzip;
                    }
                }
            }
        }
        $info = null;
        foreach ($docs as $docXml) {
            $tmp = parseDocZipXml($docXml);
            if (!empty($tmp['emitente']) || !empty($tmp['vNF'])) { $info = $tmp; break; }
        }
        $ok = in_array($cStat, ['138','139','141','656','137']) || !empty($info);
        if ($ok) {
            return [
                'success' => true,
                'origem' => 'distribuicao',
                'status' => $cStat,
                'motivo' => $xMotivo,
                'info' => $info,
                'docs_count' => count($docs),
                'cUFAutor' => $cufa,
                'steps' => $steps
            ];
        }
        $steps[] = ['cUFAutor'=>$cufa,'cStat'=>$cStat,'motivo'=>$xMotivo,'docs'=>count($docs)];
    }
    return [ 'success'=>false, 'origem'=>'distribuicao', 'steps'=>$steps ];
}

function ufFromCode(int $cUF): ?string {
    // Mínimo necessário (RR); demais retornam null e caem em SVRS
    $map = [
        14 => 'RR',
    ];
    return $map[$cUF] ?? null;
}

function getNfceConsultaEndpoints(string $tpAmb, ?string $uf): array {
    $isHom = ($tpAmb === '2');
    $urls = [];
    // Preferir endpoint do estado quando conhecido
    if ($uf === 'RR') {
        // Variações comuns de endpoint em RR
        $urls[] = ($isHom
            ? 'https://hom.nfce.sefaz.rr.gov.br/ws/NFeConsultaProtocolo4/NFeConsultaProtocolo4.asmx'
            : 'https://nfce.sefaz.rr.gov.br/ws/NFeConsultaProtocolo4/NFeConsultaProtocolo4.asmx');
        $urls[] = ($isHom
            ? 'https://hom.nfce.sefaz.rr.gov.br/ws/NfeConsultaProtocolo4/NfeConsultaProtocolo4.asmx'
            : 'https://nfce.sefaz.rr.gov.br/ws/NfeConsultaProtocolo4/NfeConsultaProtocolo4.asmx');
        $urls[] = ($isHom
            ? 'https://hom.nfce.sefaz.rr.gov.br/ws/NFeConsultaProtocolo/NFeConsultaProtocolo4.asmx'
            : 'https://nfce.sefaz.rr.gov.br/ws/NFeConsultaProtocolo/NFeConsultaProtocolo4.asmx');
        $urls[] = ($isHom
            ? 'https://hom.nfce.sefaz.rr.gov.br/ws/NfeConsultaProtocolo/NfeConsultaProtocolo4.asmx'
            : 'https://nfce.sefaz.rr.gov.br/ws/NfeConsultaProtocolo/NfeConsultaProtocolo4.asmx');
        $urls[] = ($isHom
            ? 'https://www.sefaz.rr.gov.br/nfce/ws/NFeConsultaProtocolo4/NFeConsultaProtocolo4.asmx'
            : 'https://www.sefaz.rr.gov.br/nfce/ws/NFeConsultaProtocolo4/NFeConsultaProtocolo4.asmx');
        $urls[] = ($isHom
            ? 'https://www.sefaz.rr.gov.br/nfce/ws/NfeConsultaProtocolo4/NfeConsultaProtocolo4.asmx'
            : 'https://www.sefaz.rr.gov.br/nfce/ws/NfeConsultaProtocolo4/NfeConsultaProtocolo4.asmx');
        $urls[] = ($isHom
            ? 'https://www.sefaz.rr.gov.br/nfce/ws/NFeConsultaProtocolo/NFeConsultaProtocolo4.asmx'
            : 'https://www.sefaz.rr.gov.br/nfce/ws/NFeConsultaProtocolo/NFeConsultaProtocolo4.asmx');
        $urls[] = ($isHom
            ? 'https://www.sefaz.rr.gov.br/nfce/ws/NfeConsultaProtocolo/NfeConsultaProtocolo4.asmx'
            : 'https://www.sefaz.rr.gov.br/nfce/ws/NfeConsultaProtocolo/NfeConsultaProtocolo4.asmx');
        $urls[] = ($isHom
            ? 'https://www.sefaz.rr.gov.br/nfce/services/NFeConsultaProtocolo4'
            : 'https://www.sefaz.rr.gov.br/nfce/services/NFeConsultaProtocolo4');
        $urls[] = ($isHom
            ? 'https://www.sefaz.rr.gov.br/nfce/services/NfeConsultaProtocolo4'
            : 'https://www.sefaz.rr.gov.br/nfce/services/NfeConsultaProtocolo4');
        // Adiciona variante com subdomínio nfce.sefaz.rr.gov.br em /nfce/services (algumas instalações utilizam este host)
        $urls[] = 'https://nfce.sefaz.rr.gov.br/nfce/services/NfeConsultaProtocolo4';
        $urls[] = 'https://nfce.sefaz.rr.gov.br/nfce/services/NFeConsultaProtocolo4';
        // Novo host informado pelos redirects (portalapp)
        $urls[] = 'https://portalapp.sefaz.rr.gov.br/nfce/ws/NFeConsultaProtocolo4/NFeConsultaProtocolo4.asmx';
        $urls[] = 'https://portalapp.sefaz.rr.gov.br/nfce/ws/NfeConsultaProtocolo4/NfeConsultaProtocolo4.asmx';
        $urls[] = 'https://portalapp.sefaz.rr.gov.br/nfce/ws/NFeConsultaProtocolo/NFeConsultaProtocolo4.asmx';
        $urls[] = 'https://portalapp.sefaz.rr.gov.br/nfce/ws/NfeConsultaProtocolo/NfeConsultaProtocolo4.asmx';
        $urls[] = 'https://portalapp.sefaz.rr.gov.br/nfce/services/NFeConsultaProtocolo4';
        $urls[] = 'https://portalapp.sefaz.rr.gov.br/nfce/services/NfeConsultaProtocolo4';
    }
    // SVRS (fallback comum para vários estados)
    $urls[] = ($isHom
        ? 'https://homologacao.nfce.sefazrs.rs.gov.br/ws/NfeConsultaProtocolo/NfeConsultaProtocolo4.asmx'
        : 'https://nfce.svrs.rs.gov.br/ws/NFeConsultaProtocolo4/NFeConsultaProtocolo4.asmx');
    $urls[] = 'https://nfce.svrs.rs.gov.br/ws/NFeConsultaProtocolo/NFeConsultaProtocolo4.asmx';
    $urls[] = 'https://nfce.svrs.rs.gov.br/ws/NfeConsultaProtocolo4/NfeConsultaProtocolo4.asmx';
    $urls[] = 'https://nfce.svrs.rs.gov.br/ws/NfeConsultaProtocolo/NfeConsultaProtocolo4.asmx';
    return $urls;
}

function tentarConsultaProtocolo(string $pemPath, string $tpAmb, string $chave, int $cUF): ?array {
    // SOAP 1.1 (alguns servidores exigem exatamente estes headers/envelope)
    $action = 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeConsultaProtocolo4/nfeConsultaNF';
    $body = '<nfeConsultaNF xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/NFeConsultaProtocolo4">'
          . '<nfeDadosMsg>'
          . '<consSitNFe versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">'
          . '<tpAmb>' . htmlspecialchars($tpAmb) . '</tpAmb>'
          . '<xServ>CONSULTAR</xServ>'
          . '<chNFe>' . htmlspecialchars($chave) . '</chNFe>'
          . '</consSitNFe>'
          . '</nfeDadosMsg>'
          . '</nfeConsultaNF>';

    $uf = ufFromCode($cUF);
    $endpoints = getNfceConsultaEndpoints($tpAmb, $uf);

    $lastError = null;
    $cfg = carregarConfigCert();
    $debug = (bool)($cfg['debug'] ?? false);
    $debugSteps = [];
    foreach ($endpoints as $urlBase) {
        try {
            // Tenta SOAP 1.2
            $resp = curlSoapRequest($urlBase, $action, $body, $pemPath, null, 30, $cfg['ca_bundle'] ?? null, (bool)($cfg['allow_insecure_on_ca_fail'] ?? false));
            $xml = xmlExtract($resp, 'retConsSitNFe');
            if (!$xml) {
                // Tenta SOAP 1.1 caso a resposta não seja esperada
                $resp = curlSoapRequest11($urlBase, $action, $body, $pemPath, null, 30, $cfg['ca_bundle'] ?? null, (bool)($cfg['allow_insecure_on_ca_fail'] ?? false));
                $xml = xmlExtract($resp, 'retConsSitNFe');
                if (!$xml) {
                    $fault = xmlExtract($resp, 'faultstring') ?: xmlExtract($resp, 'Fault');
                    $lastError = 'Resposta sem retConsSitNFe em ' . $urlBase;
                    if ($debug) $debugSteps[] = [
                        'endpoint'=>$urlBase,
                        'result'=>'no_retConsSitNFe',
                        'fault'=>$fault,
                        'snippet'=> firstChars($resp)
                    ];
                    continue;
                }
            }
            $cStat = xmlExtract($xml, 'cStat') ?: '';
            $xMotivo = xmlExtract($xml, 'xMotivo') ?: '';
            $infProt = xmlExtract($xml, 'infProt');
            $nProt = $infProt ? xmlExtract($infProt, 'nProt') : null;
            $ok = [
                'success' => !empty($cStat),
                'origem' => 'consulta',
                'status' => $cStat,
                'motivo' => $xMotivo,
                'protocolo' => $nProt,
                'endpoint' => $urlBase
            ];
            if ($debug) $ok['debug'] = [ 'steps' => $debugSteps ];
            return $ok;
        } catch (Exception $e) {
            $lastError = $e->getMessage() . ' (' . $urlBase . ')';
            if ($debug) $debugSteps[] = ['endpoint'=>$urlBase,'error'=>$e->getMessage()];
            // tenta próximo
        }
    }
    if ($lastError) {
        if ($debug) throw new Exception(json_encode(['last_error' => $lastError, 'steps' => $debugSteps], JSON_UNESCAPED_SLASHES));
        throw new Exception($lastError);
    }
    return null;
}

try {
    $chave = isset($_POST['chave']) ? preg_replace('/[^0-9]/', '', (string)$_POST['chave']) : '';
    if (!validarChaveNFe($chave)) {
        throw new Exception('Chave inválida. Verifique os 44 dígitos.');
    }
    $dvOk = verificarDVChaveNFe($chave);

    $meta = extrairInfoChave($chave);
    $cfg = carregarConfigCert();
    $pfxPath = $cfg['path'] ?? '';
    $pfxPass = $cfg['pass'] ?? '';
    $cnpjCfg = $cfg['cnpj'] ?? $meta['cnpj'];
    $tpAmb = strtolower((string)($cfg['ambiente'] ?? 'producao')) === 'homologacao' ? '2' : '1';

    if (!$pfxPath || !$pfxPass) {
        throw new Exception('Certificado A1 não configurado. Defina path e senha em _private/config/sefaz.php');
    }

    [$pemPath, $certs] = criarPemTemporario($pfxPath, $pfxPass);
    if (!$cnpjCfg && !empty($certs['cert'])) {
        $cnpjFromCert = extractCnpjFromCert($certs['cert']);
        if ($cnpjFromCert) $cnpjCfg = $cnpjFromCert;
    }
    try {
        // 1) Tentar baixar XML completo via distribuição (pede CNPJ)
        $distDebug = null;
        try {
            $dist = tentarDistribuicaoPorChave($pemPath, $tpAmb, (int)$meta['cUF'], $cnpjCfg, $chave);
            if ($dist && ($dist['success'] ?? false)) {
                echo json_encode([
                    'success' => true,
                    'chave' => $chave,
                    'origem' => 'distribuicao',
                    'status' => $dist['status'] ?? null,
                    'motivo' => $dist['motivo'] ?? null,
                    'emitente' => $dist['info']['emitente'] ?? null,
                    'cnpj_emitente' => $dist['info']['cnpj_emitente'] ?? null,
                    'dhEmi' => $dist['info']['dhEmi'] ?? null,
                    'vNF' => $dist['info']['vNF'] ?? null,
                    'itens' => $dist['info']['itens'] ?? [],
                    'dv_ok' => $dvOk,
                    'docs_count' => $dist['docs_count'] ?? null
                ]);
                exit;
            } else {
                $distDebug = $dist;
            }
        } catch (Exception $e) {
            // Ignorar erro de distribuição e seguir para consulta de protocolo
        }

        // 2) Fallback: consulta de protocolo (SOAP) – pode não estar disponível na RR/SVRS
        $consDebug = null;
        try {
            $cons = tentarConsultaProtocolo($pemPath, $tpAmb, $chave, (int)$meta['cUF']);
            if ($cons && ($cons['success'] ?? false)) {
                echo json_encode([
                    'success' => true,
                    'chave' => $chave,
                    'origem' => 'consulta',
                    'status' => $cons['status'] ?? null,
                    'motivo' => $cons['motivo'] ?? null,
                    'protocolo' => $cons['protocolo'] ?? null,
                    'endpoint' => $cons['endpoint'] ?? null,
                    'dv_ok' => $dvOk
                ]);
                exit;
            } else {
                $consDebug = $cons;
            }
        } catch (Exception $e) {
            // Não abortar – seguir para consulta pública
            $consDebug = [ 'error' => $e->getMessage() ];
        }

        // 3) Fallback final: consulta pública (sem certificado)
        $pub = consultarPublicoRR($chave);
        if ($pub && ($pub['success'] ?? false)) {
            echo json_encode([
                'success' => true,
                'chave' => $chave,
                'origem' => 'publico',
                'emitente' => $pub['emitente'] ?? null,
                'cnpj_emitente' => $pub['cnpj_emitente'] ?? null,
                'dhEmi' => $pub['dhEmi'] ?? null,
                'vNF' => $pub['vNF'] ?? null,
                'url_publica' => $pub['url_publica'] ?? null
            ]);
            exit;
        }

        $payload = [
            'success' => false,
            'message' => 'Não foi possível obter informações desta chave no momento.',
            'dist' => $distDebug,
            'consulta' => $consDebug,
            'publico' => $pub
        ];
        throw new Exception(json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    } finally {
        if (!empty($pemPath) && file_exists($pemPath)) {
            @unlink($pemPath);
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}


