<?php
// Run with bin/php -n fpm-smoke.php /absolute/path/to/package
$package = realpath($argv[1]);
$tmp = sys_get_temp_dir() . '/php81-fpm-' . bin2hex(random_bytes(5));
mkdir($tmp, 0755);
file_put_contents("$tmp/index.php", '<?php echo json_encode([PHP_VERSION, PHP_SAPI, extension_loaded("redis"), extension_loaded("xdebug")]);');
file_put_contents("$tmp/fpm.conf", "[global]\nerror_log = $tmp/error.log\ndaemonize = no\n[smoke]\nuser = nobody\ngroup = nobody\nlisten = $tmp/fpm.sock\npm = static\npm.max_children = 1\n");
$process = proc_open([
    "$package/bin/php-fpm", '-F', '-R', '-n',
    '-d', "zend_extension=$package/modules/xdebug.so", '-y', "$tmp/fpm.conf",
], [0 => ['file', '/dev/null', 'r'], 1 => ['file', "$tmp/process.log", 'a'], 2 => ['file', "$tmp/process.log", 'a']], $pipes);
if (!is_resource($process)) {
    throw new RuntimeException('Could not start FPM');
}
function record(int $type, string $content): string {
    return pack('CCnnCC', 1, $type, 1, strlen($content), 0, 0) . $content;
}
function parameter(string $key, string $value): string {
    $out = '';
    foreach ([strlen($key), strlen($value)] as $length) {
        $out .= $length < 128 ? chr($length) : pack('N', $length | 0x80000000);
    }
    return $out . $key . $value;
}
function readExact($socket, int $length): string {
    $data = '';
    while (strlen($data) < $length) {
        $chunk = fread($socket, $length - strlen($data));
        if ($chunk === false || $chunk === '') {
            throw new RuntimeException('Incomplete FastCGI response');
        }
        $data .= $chunk;
    }
    return $data;
}
try {
    $socket = false;
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $socket = @stream_socket_client("unix://$tmp/fpm.sock", $errno, $error, 0.1);
        if ($socket !== false) break;
        if (!proc_get_status($process)['running']) {
            throw new RuntimeException(file_get_contents("$tmp/process.log"));
        }
        usleep(100000);
    }
    if ($socket === false) throw new RuntimeException('FPM did not become ready');
    stream_set_timeout($socket, 10);
    $params = '';
    foreach (['SCRIPT_FILENAME' => "$tmp/index.php", 'SCRIPT_NAME' => '/index.php',
              'REQUEST_METHOD' => 'GET', 'SERVER_PROTOCOL' => 'HTTP/1.1', 'REQUEST_URI' => '/index.php'] as $key => $value) {
        $params .= parameter($key, $value);
    }
    $request = record(1, pack('nCxxxxx', 1, 0)) . record(4, $params) . record(4, '') . record(5, '');
    if (fwrite($socket, $request) !== strlen($request)) throw new RuntimeException('FastCGI write failed');
    $response = '';
    while (true) {
        $header = unpack('Cversion/Ctype/nid/nlength/Cpadding/Creserved', readExact($socket, 8));
        $body = readExact($socket, $header['length']);
        readExact($socket, $header['padding']);
        if ($header['type'] === 6) $response .= $body;
        if ($header['type'] === 3) break;
    }
    fclose($socket);
    $body = explode("\r\n\r\n", $response, 2)[1] ?? '';
    if (json_decode($body, true) !== ['8.1.29', 'fpm-fcgi', true, true]) {
        throw new RuntimeException('Unexpected FPM response: ' . $response);
    }
    echo "PASS: real FPM request, PHP 8.1.29, Redis and Xdebug loaded\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    foreach (glob("$tmp/*") as $file) unlink($file);
    rmdir($tmp);
}
