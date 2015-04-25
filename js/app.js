/**
 * 將日期格式化成Input type=date的格式
 * @param {date} d 日期
 * @param {Number} offset 要調整的時間量(毫秒)
 * @returns {String} 年-月-日
 */
var formatDateInput = function (d, offset) {
    if (offset) {
        d.setTime(d.getTime() - offset);
    }
    var mm = d.getMonth() + 1,
            dd = d.getDate();
    if (mm < 10) {
        mm = '0' + mm;
    }
    if (dd < 10) {
        dd = '0' + dd;
    }
    return (d.getFullYear()) + '-' + mm + '-' + dd;
}

/**
 * Round到小數第4位
 * @param {Number} n
 * @returns {Number}
 */
var round4 = function (n) {
    return Math.round(n * 1000) / 1000;
}

//設定日期的預設值
d3.select('#startDate').attr('value', formatDateInput(new Date(), 86400 * 1000 * 2));
d3.select('#endDate').attr('value', formatDateInput(new Date()));

//建立日期格式化的函數
var format = d3.time.format("%Y-%m-%d");
//折線圖
var chart;

//按下查詢的事件
function query() {
    //取出參數
    var ip = document.getElementById('ip').value;
    var startDate = document.getElementById('startDate').value;
    var endDate = document.getElementById('endDate').value;
    //檢查日期是否超過100天
    var d1 = new Date(startDate);
    var d2 = new Date(endDate);
    var today = new Date();
    var diff1 = (today - d1) / 86400 / 1000;
    var diff2 = (today - d2) / 86400 / 1000;
    if ((diff1 >= 100) || (diff2 >= 100)) {
        alert('最多只能查詢3天前的資料');
        return false;
    }
    //呼叫API
    document.getElementById('submit').disabled = true;
    d3.json('flowquery.php?ip=' + ip + '&startDate=' + startDate + '&endDate=' + endDate, function (error, data) {
        document.getElementById('submit').disabled = false;
        //console.log(data);

        //處理查詢失敗的情況
        if (!data || (data.success === undefined) || !data.success) {
            console.log('查詢失敗');
            return false;
        }

        //計算每日總計、每個時段的累積流量
        data.result.forEach(function (d, i, arr) {
            var downloadTotal = 0;
            var uploadTotal = 0;
            d.flow.forEach(function (f) {
                downloadTotal += f.download * 1;
                uploadTotal += f.upload * 1;
                f.cumulativeDownload = downloadTotal;
                f.cumulativeUpload = uploadTotal;
                f.cumulativeTotal = f.cumulativeDownload + f.cumulativeUpload;
            });
            arr[i]['download'] = downloadTotal;
            arr[i]['upload'] = uploadTotal;
            arr[i]['total'] = downloadTotal + uploadTotal;
        });
        //console.log(data.result);

        //C3畫圖開始
        //取出日期，產生X軸
        var x = data.result.map(function (d) {
            return new Date(d.date.replace(/\//g, '-'));
        });
        x.unshift('x');
        //定義下載、上傳、總流量的ID
        labels = {
            download: ip + '下載流量',
            upload: ip + '上傳流量',
            total: ip + '總流量'
        };
        //取出流量
        var download = data.result.map(function (d) {
            return round4(d.download / 1000 / 1000);
        });
        download.unshift(labels['download']);
        var upload = data.result.map(function (d) {
            return round4(d.upload / 1000 / 1000);
        });
        upload.unshift(labels['upload']);
        var total = data.result.map(function (d) {
            return round4(d.total / 1000 / 1000);
        });
        total.unshift(labels['total']);

        //定義圖形種類
        var types = {};
        types[labels['download']] = 'bar';
        types[labels['upload']] = 'bar';

        //畫圖
        chart = c3.generate({
            bindto: '#chart',
            data: {
                x: 'x',
                columns: [x, total, download, upload],
                type: 'line',
                types: types,
                groups: [
                    [labels['download'], labels['upload']]
                ]
            },
            axis: {
                x: {
                    type: 'timeseries',
                    padding: {right: 100},
                    tick: {
                        format: function (x) {
                            return format(x);
                        }
                    }
                },
                y: {
                    label: '流量(MB)'
                }
            }
        });
        //C3畫圖結束

        //填表格開始
        var tbody = d3.select('#table tbody');
        tbody.selectAll('tr').remove();
        var tr = tbody.selectAll('tr')
                .data(data.result).enter()
                .append('tr');
        tr.selectAll('td')
                .data(function (d) {
                    return [d.date, d.download, d.upload, d.total];
                }).enter()
                .append('td')
                .text(function (d) {
                    return d;
                });
        //填表格結束
    });
    return false;
}
