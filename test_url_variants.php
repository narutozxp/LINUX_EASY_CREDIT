<?php
/**
 * 测试不同 URL 协议组合的签名
 * 用于排查 HTTP/HTTPS 导致的签名问题
 */

require_once __DIR__ . '/api/EpayHelper.php';

// 加载配置
$config = require __DIR__ . '/config/config.php';

echo "==========================================\n";
echo "URL 协议组合签名测试\n";
echo "==========================================\n\n";

// 测试参数（固定）
$baseParams = [
    'pid' => $config['epay']['pid'],
    'type' => 'epay',
    'out_trade_no' => 'TEST20250101',
    'name' => '测试',
    'money' => 0.01,
];

// 不同的 URL 组合
$urlVariants = [
    'A' => [
        'notify_url' => 'http://donate.momb.top/api/notify.php',
        'return_url' => 'http://donate.momb.top/success.html',
        'desc' => 'notify=HTTP, return=HTTP'
    ],
    'B' => [
        'notify_url' => 'https://donate.momb.top/api/notify.php',
        'return_url' => 'https://donate.momb.top/success.html',
        'desc' => 'notify=HTTPS, return=HTTPS'
    ],
    'C' => [
        'notify_url' => 'http://donate.momb.top/api/notify.php',
        'return_url' => 'https://donate.momb.top/success.html',
        'desc' => 'notify=HTTP, return=HTTPS (当前配置)'
    ],
    'D' => [
        'notify_url' => 'https://donate.momb.top/api/notify.php',
        'return_url' => 'http://donate.momb.top/success.html',
        'desc' => 'notify=HTTPS, return=HTTP'
    ],
];

echo "当前 config.php 配置：\n";
echo "  notify_url: " . $config['epay']['notify_url'] . "\n";
echo "  return_url: " . $config['epay']['return_url'] . "\n\n";

echo "==========================================\n";
echo "不同协议组合的签名结果：\n";
echo "==========================================\n\n";

$helper = new EpayHelper($config['epay']);

foreach ($urlVariants as $key => $variant) {
    $testParams = array_merge($baseParams, [
        'notify_url' => $variant['notify_url'],
        'return_url' => $variant['return_url']
    ]);

    // 生成签名
    $sign = $helper->createSign($testParams);

    // 生成签名字符串（用于验证）
    ksort($testParams);
    $signString = '';
    foreach ($testParams as $k => $v) {
        $signString .= $k . '=' . $v . '&';
    }
    $signString = rtrim($signString, '&') . $config['epay']['key'];

    echo "组合 {$key}: {$variant['desc']}\n";
    echo "  签名: {$sign}\n";

    if ($key === 'C') {
        echo "  👆 这是当前配置的签名\n";
    }
    echo "\n";
}

echo "==========================================\n";
echo "使用说明：\n";
echo "==========================================\n";
echo "1. 找到与当前配置匹配的组合（标记为 '当前配置'）\n";
echo "2. 在浏览器测试支付时，查看错误信息\n";
echo "3. 如果签名失败，尝试在控制台修改 URL 协议\n";
echo "4. 或者修改 config.php 中的 URL 协议以匹配控制台\n\n";

echo "常见情况：\n";
echo "- 如果网站使用 HTTPS，建议统一使用 HTTPS\n";
echo "- 如果网站使用 HTTP，建议统一使用 HTTP\n";
echo "- notify_url 和 return_url 可以使用不同协议\n";
echo "  但必须与控制台配置完全一致\n";
