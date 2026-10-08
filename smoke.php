<?php
// Run with: bin/php -n -d zend_extension=modules/xdebug.so smoke.php extensions.json
function check(bool $ok, string $name): void {
    if (!$ok) {
        throw new RuntimeException($name);
    }
    echo "PASS: $name\n";
}

check(PHP_VERSION === '8.1.29', 'PHP version');
foreach (json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR) as $extension) {
    if ($extension === 'mbregex') {
        check(function_exists('mb_ereg'), 'mbregex');
    } else {
        check(extension_loaded($extension === 'opcache' ? 'Zend OPcache' : $extension), $extension);
    }
}
check(!extension_loaded('event'), 'event extension absent');
check(phpversion('xdebug') === '3.4.7', 'Xdebug version');
check(in_array('mysql', PDO::getAvailableDrivers(), true), 'PDO MySQL driver');
check(class_exists('Redis') && class_exists('AMQPConnection'), 'Redis and AMQP classes');
$db = new SQLite3(':memory:');
$db->exec('CREATE TABLE smoke (value TEXT)');
$db->exec("INSERT INTO smoke VALUES ('ok')");
check($db->querySingle('SELECT value FROM smoke') === 'ok', 'SQLite query');
$value = ['text' => '中文', 'number' => 123];
check(msgpack_unpack(msgpack_pack($value)) === $value, 'MessagePack round trip');
check(gmp_strval(gmp_add('999999999999999999', '1')) === '1000000000000000000', 'GMP');
check((new Collator('en_US'))->compare('a', 'b') < 0, 'ICU collation');
check(mb_ereg('测试', '测试成功'), 'multibyte regular expression');
check(openssl_digest('test', 'sha256') === hash('sha256', 'test'), 'OpenSSL digest');
$key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
check(sodium_crypto_secretbox_open(sodium_crypto_secretbox('ok', $nonce, $key), $nonce, $key) === 'ok', 'Sodium round trip');
$im = imagecreatetruecolor(4, 4);
ob_start();
imagewebp($im);
$webp = ob_get_clean();
check(str_contains($webp, 'WEBP') && function_exists('imagettftext'), 'GD WebP and FreeType');
$image = new Imagick();
$image->newImage(4, 4, 'red');
$image->setImageFormat('png');
check(str_starts_with($image->getImageBlob(), "\x89PNG"), 'Imagick PNG encode');
$xml = new DOMDocument();
$xml->loadXML('<root>ok</root>');
$xsl = new DOMDocument();
$xsl->loadXML('<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform"><xsl:output method="text"/><xsl:template match="/"><xsl:value-of select="root"/></xsl:template></xsl:stylesheet>');
$processor = new XSLTProcessor();
$processor->importStylesheet($xsl);
check($processor->transformToXML($xml) === 'ok', 'XSL transformation');
libxml_use_internal_errors(true);
check(!(new DOMDocument())->loadXML('<root>'), 'invalid XML rejected');
check(libxml_get_last_error() instanceof LibXMLError && count(libxml_get_errors()) > 0, 'libxml error callback');
libxml_clear_errors();
libxml_use_internal_errors(false);
$path = tempnam(sys_get_temp_dir(), 'php81-zip-');
try {
    $zip = new ZipArchive();
    check($zip->open($path, ZipArchive::OVERWRITE) === true, 'ZIP create');
    $zip->addFromString('test.txt', 'ok');
    $zip->close();
    $zip->open($path);
    check($zip->getFromName('test.txt') === 'ok', 'ZIP read');
    $zip->close();
} finally {
    unlink($path);
}
echo "All CLI checks passed on ", php_uname('s'), ' ', php_uname('m'), ".\n";
