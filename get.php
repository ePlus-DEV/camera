<?php
$url = $_GET['url'];
$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36');

$headers = [
    'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
    'Accept-Language: en-US,en;q=0.9,vi;q=0.8',
    'Cache-Control: no-cache',
    'Connection: keep-alive',
    'Cookie: .VDMS=81FF038CBEDEA0DB6C73F5C6F8F76D2B9D597EA958C998721BEA844805F4970C84D8EC3A9FDE23FFFD95373D70F47B65FC10D4FF2567251F874F1ED77FF21C320AEDDB6648B24D01CC89FDE2C29BFD9B6E3B60B9C07E230C11828BC65787CF5F1A4336FE6A9230A1295C3D3C1D3CB9591AC72424; _frontend=!eBwYtMTH4yPaChX3xOTVoJth7yoQDtAU7fXqKBaZ6j4smASoyTzJ4PxZfzi0Nz7dPEvyORi3h6Nnsoo=; CurrentLanguage=vi; TS01e7700a=0150c7cfd18ded1f9633d93c69c172604eb3608734f67183baf2b5e77fceb3be76636d8032c93b2ad4a9f76b9036c0c590a25010d23442d0394ec5195b1e29624c41feab1a; TS3d206961027=08a5ec0864ab2000cafe4646a7928a5e261cddbc6422b96acfa87a25449969f920083c406b2df2ec08c010a1cc11300036ba9a65046469f0bd91018b84d0a34ca61084fdc25a819fd5fa3120a3b777f8c1cd45fb45acd6149b6fac4290a877d3',
    'Pragma: no-cache',
    'Referer: http://giaothong.hochiminhcity.gov.vn/'
];

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$file = curl_exec($ch);
curl_close($ch);

header('Content-Type: image/jpeg');
echo $file;
