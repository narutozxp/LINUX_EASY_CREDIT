<?php
/**
 * 深度调试工具 - 检查所有可能导致签名失败的问题
 */

require_once __DIR__ . '/api/EpayHelper.php';

echo "==========================================\n";
echo "签名验证失败 - 深度调试\n";
echo "==========================================\n\n";

// 加载配置
$config = require __DIR__ . '/config/config.php';

echo "1. 检查配置文件\n";
echo "-------------------------------------------\n";
$pid = $config['epay']['pid'];
$key = $config['epay']['key'];

echo "PID 长度: " . strlen($pid) . " 字符\n";
echo "PID 前10位: " . substr($pid, 0, 10) . "...\n";
echo "PID 后10位: ..." . substr($pid, -10) . "\n";
echo "PID 是否有空格: " . (strpos($pid, ' ') !== false ? '是 ❌' : '否 ✅') . "\n";
echo "PID 是否有换行: " . (strpos($pid, "\n") !== false || strpos($pid, "\r") !== false ? '是 ❌' : '否 ✅') . "\n\n";

echo "Key 长度: " . strlen($key) . " 字符\n";
echo "Key 前10位: " . substr($key, 0, 10) . "...\n";
echo "Key 后10位: ..." . substr($key, -10) . "\n";
echo "Key 是否有空格: " . (strpos($key, ' ') !== false ? '是 ❌' : '否 ✅') . "\n";
echo "Key 是否有换行: " . (strpos($key, "\n") !== false || strpos($key, "\r") !== false ? '是 ❌' : '否 ✅') . "\n\n";

echo "2. 测试简单订单签名\n";
echo "-------------------------------------------\n";

$helper = new EpayHelper($config['epay']);

// 使用官方示例相同的参数结构
$testParams = [
    'pid' => $pid,
    'type' => 'epay',
    'out_trade_no' => 'TEST001',
    'name' => 'Test',
    'money' => '10',  // 字符串格式
];

echo "测试参数（字符串格式）：\n";
foreach ($testParams as $k => $v) {
    echo "  {$k} = {$v} (类型: " . gettype($v) . ")\n";
}

$sign1 = $helper->createSign($testParams);
echo "\n生成的签名: {$sign1}\n\n";

// 手动验证签名过程
echo "3. 手动验证签名过程\n";
echo "-------------------------------------------\n";

$sortedParams = $testParams;
ksort($sortedParams);

echo "排序后的参数：\n";
foreach ($sortedParams as $k => $v) {
    echo "  {$k} = {$v}\n";
}

$signString = '';
foreach ($sortedParams as $k => $v) {
    $signString .= $k . '=' . $v . '&';
}
$signString = rtrim($signString, '&');

echo "\n待签名字符串：\n  {$signString}\n";
echo "\n追加密钥后：\n  {$signString}{$key}\n";
echo "\nMD5 结果: " . md5($signString . $key) . "\n\n";

// 测试数字格式
echo "4. 测试不同的 money 格式\n";
echo "-------------------------------------------\n";

$moneyFormats = [
    '字符串 "10"' => '10',
    '字符串 "10.00"' => '10.00',
    '浮点数 10.0' => 10.0,
    '整数 10' => 10,
];

foreach ($moneyFormats as $desc => $money) {
    $params = [
        'pid' => $pid,
        'type' => 'epay',
        'out_trade_no' => 'TEST001',
        'name' => 'Test',
        'money' => $money,
    ];
    $sign = $helper->createSign($params);
    echo "{$desc}: {$sign}\n";
}

echo "\n5. 测试中文名称\n";
echo "-------------------------------------------\n";

$nameTests = [
    '纯英文' => 'Test',
    '中文' => '测试',
    '中文+英文' => '测试Test',
    '中文+符号' => '打赏支持：测试',
];

foreach ($nameTests as $desc => $name) {
    $params = [
        'pid' => $pid,
        'type' => 'epay',
        'out_trade_no' => 'TEST001',
        'name' => $name,
        'money' => '10',
    ];
    $sign = $helper->createSign($params);
    echo "{$desc} ({$name}): {$sign}\n";
}

echo "\n6. 完整的请求参数示例\n";
echo "-------------------------------------------\n";

$finalParams = [
    'pid' => $pid,
    'type' => 'epay',
    'out_trade_no' => 'RW' . date('YmdHis') . rand(1000, 9999),
    'name' => 'Test Order',
    'money' => '0.01',
];

$finalParams['sign'] = $helper->createSign($finalParams);
$finalParams['sign_type'] = 'MD5';

echo "完整参数：\n";
foreach ($finalParams as $k => $v) {
    echo "  {$k}: {$v}\n";
}

echo "\nURL编码后的查询字符串：\n";
echo http_build_query($finalParams) . "\n";

echo "\n==========================================\n";
echo "调试完成\n";
echo "==========================================\n";
echo "\n如果签名仍然失败，请检查：\n";
echo "1. PID 和 Key 是否从控制台正确复制（没有多余空格）\n";
echo "2. 控制台中是否有其他特殊配置\n";
echo "3. 是否使用了正确的应用（如果有多个应用）\n";
