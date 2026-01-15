<?php
/**
 * 经济研究员全量本地化版：自动化存档、本地绘图与智能搜索
 */

set_time_limit(600); 
ini_set('memory_limit', '512M');

$data_dir = __DIR__ . '/market_data'; 
$html_dir = __DIR__ . '/market_pages'; 
if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);
if (!is_dir($html_dir)) mkdir($html_dir, 0755, true);

// 1. 获取币安所有数据
$api_res = file_get_contents('https://api.binance.com/api/v3/ticker/24hr');
$all_market = json_decode($api_res, true);

// 2. 筛选所有 USDT 交易对并按成交额排序
$usdt_pairs = array_filter($all_market, function($item) {
    return str_ends_with($item['symbol'], 'USDT') && !str_contains($item['symbol'], 'UP') && !str_contains($item['symbol'], 'DOWN');
});
usort($usdt_pairs, function($a, $b) {
    return (float)$b['quoteVolume'] <=> (float)$a['quoteVolume'];
});

$index_rows = "";
$today = date('Y-m-d');
$now_time = date('Y-m-d H:i:s');
$total_count = count($usdt_pairs);

echo "正在同步数据并生成本地档案，共计 {$total_count} 个币种...\n";

foreach ($usdt_pairs as $d) {
    $symbol = $d['symbol'];
    $name = str_replace('USDT', '', $symbol);
    $price = (float)$d['lastPrice'];
    $change = (float)$d['priceChangePercent'];
    $high = (float)$d['highPrice'];
    $low = (float)$d['lowPrice'];
    $volume = round((float)$d['quoteVolume'] / 1000000, 2); 

    // --- 【本地 CSV 记录】 ---
    $csv_path = "{$data_dir}/{$name}.csv";
    if (!file_exists($csv_path)) {
        file_put_contents($csv_path, "date,close,change,high,low,volume\n");
    }
    $csv_content = file_get_contents($csv_path);
    if (strpos($csv_content, $today) === false) {
        file_put_contents($csv_path, "{$today},{$price},{$change},{$high},{$low},{$volume}\n", FILE_APPEND);
    }

    // --- 【处理历史数据：区分图表和表格】 ---
    $history_raw = array_filter(explode("\n", file_get_contents($csv_path)));
    array_shift($history_raw); // 移除 CSV 表头

    // 图表需要：从旧到新 (Chronological)
    $chart_json = json_encode(array_values($history_raw));

    // 表格需要：从新到旧 (Reverse Chronological)
    $table_history = array_reverse($history_raw);
    
    $detail_filename = "{$name}.html";
    generateProfessionalPage($name, $symbol, $price, $change, $high, $low, $volume, $now_time, $table_history, $chart_json, "{$html_dir}/{$detail_filename}");

    // --- 【首页行】 ---
    $c_style = $change >= 0 ? 'color:#27ae60' : 'color:#e74c3c';
    $index_rows .= "
    <tr class='coin-row' data-name='{$name}'>
        <td><a href='market_pages/{$detail_filename}'><strong>{$name}</strong></a></td>
        <td><strong>\${$price}</strong></td>
        <td style='{$c_style}'>{$change}%</td>
        <td>\${$volume}M</td>
        <td><a href='market_pages/{$detail_filename}' class='btn'>本地分析</a></td>
    </tr>";
}

// 3. 生成 index.html (包含增强搜索功能)
$index_html = <<<EOD
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>加密资产本地数据库 - 智能查询系统</title>
    <style>
        body { font-family: "Segoe UI", sans-serif; max-width: 1000px; margin: 40px auto; background: #f4f7f6; color: #2d3436; }
        .card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .search-container { margin: 25px 0; position: sticky; top: 10px; z-index: 100; }
        #searchInput { 
            width: 100%; padding: 18px; border: 3px solid #0984e3; border-radius: 12px; 
            font-size: 18px; outline: none; box-shadow: 0 8px 20px rgba(9,132,227,0.15);
            box-sizing: border-box;
        }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 14px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #f8f9fa; position: sticky; top: 78px; z-index: 90; font-size: 13px; color: #636e72; }
        .btn { background: #0984e3; color: white; padding: 8px 14px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: bold; }
        .hidden { display: none; }
        .count-info { color: #636e72; font-size: 14px; margin-bottom: 15px; background: #eef2f7; padding: 10px; border-radius: 8px; display: inline-block; }
    </style>
</head>
<body>
    <div class="card">
        <h1>📊 全量本地观测数据库</h1>
        <div class="count-info">已录制本地档案：<b>{$total_count}</b> 个 | 最后同步：{$now_time}</div>
        
        <div class="search-container">
            <input type="text" id="searchInput" placeholder="🔍 快速定位本地档案 (例如输入: BTC, ETH, PEPE)..." onkeyup="filterCoins()">
        </div>

        <table>
            <thead><tr><th>资产</th><th>实时单价</th><th>24H波动</th><th>24H成交额</th><th>查看本地记录</th></tr></thead>
            <tbody id="coinTable">{$index_rows}</tbody>
        </table>
    </div>

    <script>
    function filterCoins() {
        let input = document.getElementById('searchInput').value.toUpperCase().trim();
        let rows = document.getElementsByClassName('coin-row');
        for (let i = 0; i < rows.length; i++) {
            let name = rows[i].getAttribute('data-name');
            rows[i].classList.toggle('hidden', !name.includes(input));
        }
    }
    </script>
</body>
</html>
EOD;

file_put_contents(__DIR__ . '/index.html', $index_html);
echo "✅ 全量处理完成。";

/**
 * 详情页函数：完全基于本地 CSV 绘图
 */
function generateProfessionalPage($name, $symbol, $price, $change, $high, $low, $vol, $time, $table_history, $chart_json, $save_path) {
    // 构建表格 HTML (最新在前)
    $table_rows = "";
    foreach (array_slice($table_history, 0, 30) as $line) {
        $cols = explode(',', $line);
        if (count($cols) < 6) continue;
        list($d, $p, $c, $h, $l, $v) = $cols;
        $c_color = $c >= 0 ? '#27ae60' : '#e74c3c';
        $v_range = round((float)$h - (float)$l, 2);
        $table_rows .= "<tr><td>{$d}</td><td><strong>\${$p}</strong></td><td style='color:{$c_color}'>{$c}%</td><td>\${$h}/\${$l}</td><td>\${$v_range}</td><td>{$v}M</td></tr>";
    }

    $current_range = round($high - $low, 2);

    $html = <<<EOD
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>{$name} 本地研究报告 - 经济观察</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; background: #fff; }
        .header { background: #2d3436; color: white; padding: 25px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
        .item { background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #eee; }
        .stat-val { font-size: 1.2em; font-weight: bold; display: block; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; text-align: left; }
        th { background: #f1f2f6; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <a href="../index.html" style="color:#0984e3; text-decoration:none; font-weight:bold;">← 返回全量列表</a>
            <h2 style="margin:10px 0 0 0;">{$name} 历史观察报告</h2>
        </div>
        <div style="text-align:right">
            <span style="font-size: 2em; font-weight:bold;">\${$price}</span><br>
            <small>本地存档更新时间：{$time}</small>
        </div>
    </div>

    <div class="grid">
        <div class="item"><small>24H 最高</small><span class="stat-val">\${$high}</span></div>
        <div class="item"><small>24H 最低</small><span class="stat-val">\${$low}</span></div>
        <div class="item"><small>当日波幅</small><span class="stat-val">\${$current_range}</span></div>
        <div class="item"><small>成交额</small><span class="stat-val">{$vol}M</span></div>
    </div>

    <div style="height:400px; margin-bottom:40px;">
        <canvas id="localChart"></canvas>
    </div>

    <h3>📜 本地历史存档数据</h3>
    <table>
        <thead><tr><th>记录日期</th><th>收盘价</th><th>涨跌幅</th><th>最高/最低</th><th>当日波幅</th><th>成交额</th></tr></thead>
        <tbody>{$table_rows}</tbody>
    </table>

    <script>
        // 直接解析 PHP 传来的本地 CSV 数据
        const localData = {$chart_json};
        
        const labels = [];
        const prices = [];

        localData.forEach(line => {
            const cols = line.split(',');
            if (cols.length >= 6) {
                labels.push(cols[0]); // 日期
                prices.push(parseFloat(cols[1])); // 价格，使用 parseFloat 修复绘图 bug
            }
        });

        const ctx = document.getElementById('localChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: '{$name} 本地存档价格 (USD)',
                    data: prices,
                    borderColor: '#0984e3',
                    backgroundColor: 'rgba(9, 132, 227, 0.05)',
                    borderWidth: 3,
                    pointRadius: 2,
                    fill: true,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: false, position: 'right' }
                }
            }
        });
    </script>
</body>
</html>
EOD;
    file_put_contents($save_path, $html);
}