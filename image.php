<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

$client = new Client(); // Create an instance of the Guzzle HTTP client

$url = $_GET['url'];
$query = $_GET; // Get all query parameters from the URL
unset($query['url']); // Remove 'url' parameter from the query

$headers = [
    'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
    'Accept-Encoding' => 'gzip, deflate',
    'Accept-Language' => 'en-US,en;q=0.9,vi;q=0.8',
    'Cache-Control' => 'no-cache',
    'Connection' => 'keep-alive',
    'Cookie' => '.VDMS=81FF038CBEDEA0DB6C73F5C6F8F76D2B9D597EA958C998721BEA844805F4970C84D8EC3A9FDE23FFFD95373D70F47B65FC10D4FF2567251F874F1ED77FF21C320AEDDB6648B24D01CC89FDE2C29BFD9B6E3B60B9C07E230C11828BC65787CF5F1A4336FE6A9230A1295C3D3C1D3CB9591AC72424; _frontend=!Nc2SSSIBzq7Jgn33xOTVoJth7yoQDt/r3fu0BTHRLXHnbzI8osO/bsCNhHCppDcR2rrXRneuFIhGQtU=; CurrentLanguage=vi; TS01e7700a=0150c7cfd1cb1d18acab45564a90f966744ed541aaa4ce0990b701d2d96cf57d5922098a1296586bbd0d85c761305b5aee59579f86fe42956ba245719f53a4350fae13b3ee; TS3d206961027=08a5ec0864ab2000fc39c9f9c0970c67cf2acb4bd0ad9f7565cef07dfd02bd422dfae161c0bd512c08904b5bcc11300077073f7c59fd737f87bfa646445fee3ca7d541351b1bc9383b0dd21a6c54dd9e1f02503a37409c66e2dc5d61187dc246',
    'Host' => 'giaothong.hochiminhcity.gov.vn:8007',
    'Pragma' => 'no-cache',
    'Referer' => 'http://giaothong.hochiminhcity.gov.vn/',
    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36'
];

$promise = $client->requestAsync('GET', $url, [
    'query' => $query,
    'headers' => $headers
]);

$promise->then(
    function (ResponseInterface $res) {
        header('Content-Type: image/jpeg');
        echo $res->getBody();
    },
    function (RequestException $e) {
        echo $e->getMessage() . "\n";
        echo $e->getRequest()->getMethod();
    }
)->wait(); // Wait for the promise to complete
