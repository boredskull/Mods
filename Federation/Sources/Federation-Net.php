<?php
/** Strict remote HTTP access and HTTP Signatures. */
if (!defined('SMF')) die('No direct access');

/** Resolve all A/AAAA records and reject any non-global destination. */
function federation_public_address($ip)
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
    // FILTER_FLAG_NO_PRIV_RANGE does not cover all IANA special ranges.
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $n = ip2long($ip); if ($n === false) return false;
        $u = (float) sprintf('%u', $n);
        foreach (array(array('0.0.0.0',8),array('10.0.0.0',8),array('100.64.0.0',10),array('127.0.0.0',8),
            array('169.254.0.0',16),array('172.16.0.0',12),array('192.0.0.0',24),array('192.0.2.0',24),
            array('192.168.0.0',16),array('198.18.0.0',15),array('198.51.100.0',24),array('203.0.113.0',24),
            array('224.0.0.0',4),array('240.0.0.0',4)) as $range) {
            $mask = $range[1] === 0 ? 0 : (4294967295 << (32 - $range[1])) & 4294967295;
            if (((int) $u & $mask) === ((int) sprintf('%u', ip2long($range[0])) & $mask)) return false;
        }
        return true;
    }
    $ip = strtolower($ip);
    if (preg_match('/^(::1|::|fc|fd|fe[89ab]|ff|2001:db8:)/i', $ip)) return false;
    $raw = inet_pton($ip);
    if (!$raw || strlen($raw) !== 16) return false;
    if (substr($raw, 0, 12) === str_repeat("\x00", 10) . "\xff\xff")
        return federation_public_address(inet_ntop(substr($raw, 12)));
    // Restrict to global unicast allocation (2000::/3), rejecting IPv6 transition/tunnel edge cases.
    return (ord($raw[0]) & 0xe0) === 0x20 && !in_array(substr($raw, 0, 4), array(hex2bin('20010db8'), hex2bin('20010000')), true) && substr($raw, 0, 2) !== hex2bin('2002');
}
function federation_safe_target($url)
{
    if (!is_string($url) || strlen($url) > 2048 || strpos($url, chr(92)) !== false || preg_match('/[\x00-\x20]/', $url)) return false;
    $p = parse_url($url);
    if (!is_array($p) || !isset($p['scheme'], $p['host']) || strtolower($p['scheme']) !== 'https'
        || isset($p['user']) || isset($p['pass']) || isset($p['fragment']) || (isset($p['port']) && (int) $p['port'] !== 443)) return false;
    $host = strtolower($p['host']);
    if (strlen($host) > 253 || $host[0] === '.' || substr($host, -1) === '.' || !preg_match('/^(?=.{1,253}$)[a-z0-9.-]+$/D', $host) || strpos($host, '..') !== false
        || $host === 'localhost' || substr($host, -6) === '.local' || filter_var($host, FILTER_VALIDATE_IP)) return false;
    return $host;
}
function federation_domain_denied($host)
{
    global $smcFunc;
    $q = $smcFunc['db_query']('', 'SELECT domain FROM {db_prefix}federation_blocks', array());
    $blocked = false;
    while ($row = $smcFunc['db_fetch_assoc']($q)) {
        $d = strtolower($row['domain']);
        if ($host === $d || substr($host, -strlen($d) - 1) === '.' . $d) { $blocked = true; break; }
    }
    $smcFunc['db_free_result']($q);
    return $blocked;
}
function federation_resolve_target($url)
{
    $host = federation_safe_target($url);
    if (!$host || federation_domain_denied($host)) return false;
    $ips = array();
    if (function_exists('dns_get_record')) {
        foreach (array(DNS_A, DNS_AAAA) as $type) {
            $records = @dns_get_record($host, $type);
            if (is_array($records)) foreach ($records as $rec) {
                if (isset($rec['ip'])) $ips[] = $rec['ip'];
                if (isset($rec['ipv6'])) $ips[] = $rec['ipv6'];
            }
        }
    }
    if (!$ips) return false;
    foreach ($ips as $ip) if (!federation_public_address($ip)) return false;
    return array($host, $ips[0]);
}
function federation_http($url, $method = 'GET', $payload = '', $headers = array(), $limit = 1048576, $timeout = 15)
{
    if (!function_exists('curl_init')) return array(0, '', federation_message('federation_error_curl_is_unavailable', 'cURL is unavailable'));
    $resolved = federation_resolve_target($url);
    if (!$resolved) return array(0, '', federation_message('federation_error_unsafe_or_blocked_remote_destination', 'Unsafe or blocked remote destination'));
    list($host, $ip) = $resolved;
    $ch = curl_init($url);
    $options = array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => min(5, $timeout), CURLOPT_TIMEOUT => $timeout, CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_PROXY => '',
        CURLOPT_HTTPHEADER => $headers, CURLOPT_USERAGENT => 'Federation/' . FEDERATION_VERSION . ' (+' . federation_site() . '/)',
        CURLOPT_RESOLVE => array($host . ':443:' . (strpos($ip, ':') !== false ? '[' . $ip . ']' : $ip)), CURLOPT_ENCODING => 'identity',
    );
    if (defined('CURLOPT_MAXFILESIZE')) $options[CURLOPT_MAXFILESIZE] = $limit;
    if ($method === 'POST') { $options[CURLOPT_POST] = true; $options[CURLOPT_POSTFIELDS] = $payload; }
    // Stop reading oversized payloads, including chunked responses.
    $body = '';
    $options[CURLOPT_WRITEFUNCTION] = function ($handle, $chunk) use (&$body, $limit) {
        if (strlen($body) + strlen($chunk) > $limit) return 0;
        $body .= $chunk;
        return strlen($chunk);
    };
    curl_setopt_array($ch, $options);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    return array($code, $body, $error);
}
/** Shared endpoints are usable only when advertised by the verified actor and same-origin. */
function federation_shared_inbox($actor_url, $personal, $shared)
{
    $host = federation_safe_target($actor_url);
    if (!$host || federation_safe_target($personal) !== $host || federation_safe_target($shared) !== $host) return '';
    return $shared;
}
function federation_remote_actor($url, $local = null)
{
    if (!federation_safe_target($url)) return false;
    $headers = array('Accept: application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams"');
    if ($local) { $signed = federation_signature_headers($local, $url, '', 'GET'); if (!$signed) return false; $headers = array_merge($headers, $signed); }
    list($status, $body) = federation_http($url, 'GET', '', $headers, 262144);
    if ($status !== 200) return false;
    $a = json_decode($body, true);
    if (!is_array($a) || empty($a['id']) || $a['id'] !== $url || empty($a['inbox']) || empty($a['publicKey']['publicKeyPem'])
        || !is_string($a['publicKey']['publicKeyPem']) || empty($a['publicKey']['owner']) || $a['publicKey']['owner'] !== $url
        || !federation_safe_target($a['inbox']) || strlen($a['publicKey']['publicKeyPem']) > 8192) return false;
    // Inbox and actor must share an origin, so signed follows cannot redirect delivery elsewhere.
    if (parse_url($a['inbox'], PHP_URL_HOST) !== parse_url($url, PHP_URL_HOST)) return false;
    return $a;
}
function federation_signature_headers($actor, $inbox, $payload, $method = 'POST')
{
    $private = federation_private_key($actor['private_key']);
    if ($private === false) return false;
    $parts = parse_url($inbox);
    $path = isset($parts['path']) ? $parts['path'] : '/';
    if (!empty($parts['query'])) $path .= '?' . $parts['query'];
    $date = gmdate('D, d M Y H:i:s') . ' GMT';
    $digest = 'SHA-256=' . base64_encode(hash('sha256', $payload, true));
    $host = strtolower($parts['host']);
    $signing = '(request-target): ' . strtolower($method) . ' ' . $path . "\n" . 'host: ' . $host . "\n" . 'date: ' . $date;
    $names = '(request-target) host date';
    if ($method === 'POST') { $signing .= "\n" . 'digest: ' . $digest; $names .= ' digest'; }
    if (!openssl_sign($signing, $signature, $private, OPENSSL_ALGO_SHA256)) return false;
    $keyid = federation_actor_url($actor['actor_type'], (int) $actor['id_local']) . '#main-key';
    $headers = array('Host: ' . $host, 'Date: ' . $date,
        'Signature: keyId="' . $keyid . '",algorithm="rsa-sha256",headers="' . $names . '",signature="' . base64_encode($signature) . '"');
    if ($method === 'POST') { $headers[] = 'Digest: ' . $digest; $headers[] = 'Content-Type: application/activity+json'; }
    return $headers;
}
function federation_verify_inbox($raw, $activity, $local = null)
{
    if (strlen($raw) > 262144 || !is_array($activity) || empty($activity['actor']) || !is_string($activity['actor'])) return false;
    $date = isset($_SERVER['HTTP_DATE']) ? $_SERVER['HTTP_DATE'] : '';
    $stamp = strtotime($date);
    if (!$stamp || abs(time() - $stamp) > 300) return false;
    $digest = isset($_SERVER['HTTP_DIGEST']) ? $_SERVER['HTTP_DIGEST'] : '';
    if (!hash_equals('SHA-256=' . base64_encode(hash('sha256', $raw, true)), $digest)) return false;
    $signature = isset($_SERVER['HTTP_SIGNATURE']) ? $_SERVER['HTTP_SIGNATURE'] : '';
    if (strlen($signature) > 8192) return false;
    $pieces = array();
    foreach (explode(',', $signature) as $part) if (preg_match('/^\s*([a-zA-Z]+)="([^"]*)"\s*$/D', $part, $m)) $pieces[$m[1]] = $m[2];
    if (!isset($pieces['keyId'], $pieces['headers'], $pieces['signature'])) return false;
    if (!empty($pieces['algorithm']) && !in_array(strtolower($pieces['algorithm']), array('rsa-sha256','hs2019'), true)) return false;
    $actor = federation_remote_actor($activity['actor'], $local);
    if (!$actor || !isset($actor['publicKey']['id']) || $actor['publicKey']['id'] !== $pieces['keyId']) return false;
    $path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if ($path === '' || strlen($path) > 4096) return false;
    $host = strtolower(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
    if ($host !== strtolower(parse_url(federation_site(), PHP_URL_HOST))) return false;
    $message = federation_signed_request_string($pieces['headers'], $_SERVER);
    if ($message === false) return false;
    $binary = base64_decode($pieces['signature'], true);
    if ($binary === false) return false;
    return openssl_verify($message, $binary, $actor['publicKey']['publicKeyPem'], OPENSSL_ALGO_SHA256) === 1 ? $actor : false;
}

function federation_signed_request_string($names, $server)
{
    $names = preg_split('/\s+/', strtolower(trim($names)));
    if (count($names) > 20 || count(array_unique($names)) !== count($names) || array_diff(array('(request-target)','host','date','digest'), $names)) return false;
    $lines = array();
    foreach ($names as $name) {
        if ($name === '(request-target)') $value = strtolower($server['REQUEST_METHOD'] ?? 'post') . ' ' . ($server['REQUEST_URI'] ?? '');
        else {
            if (!preg_match('/^[a-z0-9-]+$/D', $name)) return false;
            $key = strtoupper(str_replace('-', '_', $name));
            if (!in_array($key, array('CONTENT_TYPE','CONTENT_LENGTH'), true)) $key = 'HTTP_' . $key;
            if (!isset($server[$key]) || !is_string($server[$key])) return false;
            $value = trim($server[$key]);
        }
        if ($value === '' || preg_match('/[\r\n]/', $value)) return false;
        $lines[] = $name . ': ' . $value;
    }
    return implode("\n", $lines);
}
function federation_resolve_handle($target)
{
    if (!preg_match('~^@?([^@/\s]+)@([a-z0-9.-]+)$~iD', $target, $m)) return $target;
    $url = 'https://' . strtolower($m[2]) . '/.well-known/webfinger?resource=' . rawurlencode('acct:' . $m[1] . '@' . $m[2]);
    list($status,$body) = federation_http($url, 'GET', '', array('Accept: application/jrd+json'), 65536);
    if ($status !== 200) return false;
    $j = json_decode($body,true);
    if (!is_array($j) || !isset($j['links']) || !is_array($j['links'])) return false;
    foreach (($j['links'] ?? array()) as $link) if (is_array($link) && ($link['rel'] ?? '') === 'self' && isset($link['href']) && federation_safe_target($link['href']) && in_array($link['type'] ?? '', array('application/activity+json','application/ld+json; profile="https://www.w3.org/ns/activitystreams"'),true)) return $link['href'];
    return false;
}
