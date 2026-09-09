<?php
error_reporting(0);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$self_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];

if (isset($_GET['CDN'])) {
    $CDN = base64_decode($_GET['CDN']);

    if (!$CDN || !filter_var($CDN, FILTER_VALIDATE_URL)) {
        exit;
    }

    $ref = isset($_GET['REF']) ? base64_decode($_GET['REF']) : 'https://dlive.sx/';

    $ch = curl_init($CDN);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            "Referer: {$ref}",
            'Origin: https://dlive.sx'
        ]
    ]);
    $response = curl_exec($ch);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if (
        strpos($content_type, 'application/vnd.apple.mpegurl') !== false ||
        strpos($content_type, 'application/x-mpegURL') !== false ||
        strpos($CDN, '.m3u8') !== false
    ) {
        $lines = explode("\n", $response);
        $parsed_url = parse_url($CDN);
        $domain_base = $parsed_url['scheme'] . '://' . $parsed_url['host'];
        $folder_base = substr($CDN, 0, strrpos($CDN, '/') + 1);

        foreach ($lines as &$line) {
            $line = trim($line);
            if ($line === '') continue;

            if (strpos($line, '#') === 0) {
                if (preg_match('/URI="(.*?)"/', $line, $uriMatch)) {
                    $originalUri = $uriMatch[1];
                    if (!preg_match('/^https?:\/\//', $originalUri)) {
                        $fullUri = (substr($originalUri, 0, 1) === '/') ? $domain_base . $originalUri : $folder_base . $originalUri;
                    } else {
                        $fullUri = $originalUri;
                    }
                    $proxiedUri = $self_url . '?CDN=' . base64_encode($fullUri) . '&REF=' . urlencode(base64_encode($ref));
                    $line = str_replace($originalUri, $proxiedUri, $line);
                }
                continue;
            }

            if (!preg_match('/^https?:\/\//', $line)) {
                if (substr($line, 0, 1) === "/") {
                    $line = $domain_base . $line;
                } else {
                    $line = $folder_base . $line;
                }
            }

            $line = $self_url . '?CDN=' . base64_encode($line) . '&REF=' . urlencode(base64_encode($ref));
        }

        header("Content-Type: application/vnd.apple.mpegurl");
        echo implode("\n", $lines);
        exit;
    }

    if ($content_type) {
        header("Content-Type: $content_type");
    } else {
        header("Content-Type: video/mp2t");
    }
    echo $response;
    exit;
}

if (empty($_GET['ID'])) {
    exit;
}

$Live = urlencode($_GET['ID']);

$ch = curl_init("https://dlive.sx/stream/stream-{$Live}.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING       => '',
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: tr-TR,tr;q=0.5',
        'Connection: keep-alive',
        "Referer: https://dlive.sx/watch.php?id={$Live}",
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ]
]);

$site = curl_exec($ch);
curl_close($ch);

$Data = "";
if (preg_match('#iframe src="(.*?)"#i', $site, $match)) {
    $Data = $match[1];
    if (strpos($Data, 'http') !== 0) {
        $Data = "https://dlive.sx/" . ltrim($Data, '/');
    }
}

if (empty($Data)) {
    exit;
}

$ch1 = curl_init($Data);
curl_setopt_array($ch1, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING       => '',
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: tr-TR,tr;q=0.5',
        'Connection: keep-alive',
        "Referer: https://dlive.sx/stream/stream-{$Live}.php",
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ]
]);

$site1 = curl_exec($ch1);
curl_close($ch1);

if (preg_match("#source:\s*window\.atob\('([^']+)'\)#i", $site1, $match)) {
    $base64Link = $match[1];
    $base64Ref = base64_encode($Data);
    header("Location: " . $self_url . "?CDN=" . $base64Link . "&REF=" . urlencode($base64Ref));
    exit;
}
?>