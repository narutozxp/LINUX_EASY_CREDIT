<?php
/**
 * 测试不包含 notify_url 和 return_url 的签名
 * 根据官方文档示例，这两个参数可能是可选的
 */

require_once __DIR__ . '/api/EpayHelper.php';

// 加载配置
$config = require __DIR__ . '/config/config.php';
$helper = new EpayHelper($config['epay']);

echo "==========================================\n";
echo "对比测试：包含 vs 不包含 回调 URL\n";
echo "==========================================\n\n";

$baseParams = [
    'pid' => $config['epay']['pid'],
    'type' => 'epay',
    'out_trade_no' => 'TEST20250101',
    'name' => '测试订单',
    'money' => 0.01,
];

echo "测试 1: 包含 notify_url 和 return_url（当前方式）\n";
echo "-------------------------------------------\n";
$paramsWithUrls = array_merge($baseParams, [
    'notify_url' => $config['epay']['notify_url'],
    'return_url' => $config['epay']['return_url'],
]);
$signWithUrls = $helper->createSign($paramsWithUrls);

ksort($paramsWithUrls);
$stringWithUrls = '';
foreach ($paramsWithUrls as $k => $v) {
    $stringWithUrls .= $k . '=' . $v . '&';
}
$stringWithUrls = rtrim($stringWithUrls, '&');

echo "参数: " . $stringWithUrls . "\n";
echo "签名: {$signWithUrls}\n\n";

echo "测试 2: 不包含 notify_url 和 return_url（官方示例方式）\n";
echo "-------------------------------------------\n";
$paramsWithoutUrls = $baseParams;
$signWithoutUrls = $helper->createSign($paramsWithoutUrls);

ksort($paramsWithoutUrls);
$stringWithoutUrls = '';
foreach ($paramsWithoutUrls as $k => $v) {
    $stringWithoutUrls .= $k . '=' . $v . '&';
}
$stringWithoutUrls = rtrim($stringWithoutUrls, '&');

echo "参数: " . $stringWithoutUrls . "\n";
echo "签名: {$signWithoutUrls}\n\n";

echo "==========================================\n";
echo "官方文档说明：\n";
echo "==========================================\n";
echo "- notify_url: 可选，仅参与签名\n";
echo "- return_url: 可选，仅参与签名\n";
echo "- 官方请求示例中未包含这两个参数\n\n";

echo "建议尝试：\n";
echo "1. 首先尝试不传递 notify_url 和 return_url\n";
echo "2. 如果还是失败，可能需要检查其他参数\n";
