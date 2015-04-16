<?php
//設定最大執行時間為180秒
set_time_limit(180);

//PHP 5.2才有json_encode，為了能夠支援5.2以前的版本，所以用了這段程式碼
//來源: http://www.stetsenko.net/2009/09/php-json_encode-before-5-2-0/
if (!function_exists('json_encode')) {
    function json_encode($a = false) {
        if (is_null($a))
            return 'null';
        if ($a === false)
            return 'false';
        if ($a === true)
            return 'true';
        if (is_scalar($a)) {
            if (is_float($a)) {
                // Always use "." for floats.
                return floatval(str_replace(",", ".", strval($a)));
            }

            if (is_string($a)) {
                static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
                return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
            } else
                return $a;
        }
        $isList = true;
        for ($i = 0, reset($a); $i < count($a); $i++, next($a)) {
            if (key($a) !== $i) {
                $isList = false;
                break;
            }
        }
        $result = array();
        if ($isList) {
            foreach ($a as $v)
                $result[] = json_encode($v);
            return '[' . join(',', $result) . ']';
        } else {
            foreach ($a as $k => $v)
                $result[] = json_encode($k) . ':' . json_encode($v);
            return '{' . join(',', $result) . '}';
        }
    }

}

/**
 * 送出一個POST要求到對外網路流量查詢
 * @param array $data 要POST的資料
 * @return string HTML
 */
function postToFlowquery($data = array()) {
    $ch = curl_init();
    $options = array(
        CURLOPT_URL => "http://network.ntust.edu.tw/flowstatistical.aspx",
        CURLOPT_HEADER => 0,
        CURLOPT_VERBOSE => 0,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => "Mozilla/4.0 (compatible;)",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
    );
    curl_setopt_array($ch, $options);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

/**
 * 取得兩字串中間的字串
 * @param string $str 要搜尋的字串
 * @param string $str1 字串1
 * @param string $str2 字串2
 * @return string 中間的字串，如果傳回FALSE表示找不到
 */
function getStringBetweenStrings($str, $str1, $str2) {
    $pos1 = strpos($str, $str1);
    if ($pos1 === false) {
        return false;
    }
    $pos1 += strlen($str1);
    $pos2 = strpos($str, $str2, $pos1);
    if ($pos2 === false) {
        return false;
    }
    return substr($str, $pos1, $pos2 - $pos1);
}

/**
 * 查詢指定IP與日期的流量
 * @param string $ip 要查詢的IP
 * @param string $date 要查詢的日期
 * @return int 流量，如果發生錯誤將傳回false
 */
function flowQuery($ip, $date) {
    //先取得流量查詢的網頁
    $html = postToFlowquery();
    //找出所有的INPUT
    preg_match_all('/(<input.*>)/', $html, $matches);
    //print_r($matches);
    //組合中要POST的資料
    $data = array();
    foreach ($matches[0] as $match) {
        $name = getStringBetweenStrings($match, 'name="', '"');
        $value = getStringBetweenStrings($match, 'value="', '"');
        $data[$name] = $value;
    }
    //拆解日期中的月、日
    $date = explode('/', $date);
    //設定要查詢的IP跟日期
    $data['ctl00$ContentPlaceHolder1$txtip'] = $ip;
    $data['ctl00$ContentPlaceHolder1$dlmonth'] = $date[1];
    $data['ctl00$ContentPlaceHolder1$dlday'] = $date[2];
    $data['ctl00$ContentPlaceHolder1$dlhour'] = 23;
    $data['ctl00$ContentPlaceHolder1$dlminute'] = 50;
    $data['ctl00$ContentPlaceHolder1$dlcunit'] = 1;
    //print_r($result);
    //送出查詢
    $queryHtml = postToFlowquery($data);
    //拆解表格中的文字內容
    $queryHtml = getStringBetweenStrings($queryHtml, '<table', '</table>');
    preg_match_all('/<td>\s*([^<]*)\s*<\/td>/', $queryHtml, $matches);
    $result = array();
    if (isset($matches[1])) {
        //流量欄位要果過濾的文字
        $findList = array(' (bytes)', ',');
        //迴圈到580是因為1小時有6筆紀錄，一天就有24*6=144筆，每筆有4欄資料，所以144*4=576
        for ($i = 0; $i < 576; $i += 4) {
            //取出欄位中的資料
            $datetime = trim($matches[1][$i]);
            $download = $matches[1][$i + 1];
            $upload = $matches[1][$i + 2];
            $total = $matches[1][$i + 3];
            //轉換與淨化資料
            $dt = explode(' ', $datetime);
            $download = trim(str_replace($findList, '', $download)); //外對內流量
            $upload = trim(str_replace($findList, '', $upload)); //內對外流量
            $total = trim(str_replace($findList, '', $total)); //總流量
            //產生結果
            $result[] = array(
                'datetime' => $datetime,
                'date' => $dt[0],
                'time' => $dt[1],
                'download' => $download,
                'upload' => $upload,
                'total' => $total,                   
            );
        }
    }
    return $result;
}

//定義傳回結果的格式
$result = array(
    'success' => false,
    'count' => 0,
    'result' => array(),
    'error' => '',
);

//定義參數的格式
$params = array(
    'ip' => '140.118.31.56',
    'startDate' => date("Y/m/d"),
    'endDate' => date("Y/m/d"),
);

//處理傳入的參數
foreach ($params as $key => $value) {
    if (isset($_GET[$key]) and ! empty($_GET[$key])) {
        $params[$key] = $_GET[$key];
    }
}

try {
    //處理開始日期與結束日期
    $startDate = strtotime($params['startDate']);
    $endDate = strtotime($params['endDate']);
    if ($startDate === false or $endDate === false) {
        throw new Exception('startDate或endDate格式錯誤');
    }
    //算出兩天差多少
    $count = ($endDate - $startDate) / 86400 + 1;
    //查詢這一段時間內的流量
    for ($i = 0; $i < $count; $i++) {
        //將日期格式化
        $date = date('Y/m/d', $startDate); //格式yyyy/mm/dd
        $date = date('Y/n/j', $startDate); //格式yyyy/m/d
        //查詢
        $startTime = microtime(true);
        $flow = flowquery($params['ip'], $date);
        $endTime = microtime(true);
        //如果查詢結果發生錯誤，就跳離迴圈
        if ($flow === false) {
            break;
        }
        //儲存查詢結果
        $result['result'][] = array(
            'ip' => $params['ip'],
            'date' => $date,
            'flow' => $flow,
            'spentTime' => $endTime - $startTime,
        );
        //將開始日期加1天
        $startDate += 86400;
    }
    //計算出結果數量
    $result['count'] = count($result['result']);
    //將結果設定為成功查詢
    $result['success'] = true;
} catch (Exception $exc) {
    $result['error'] = $exc->getMessage();
}

//印出結果
echo json_encode($result);
?>