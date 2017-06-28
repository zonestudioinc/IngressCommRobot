<?php

/**
 * ingress
 */
class Ingress
{
    // Request Header
    private $header = [];
    // Whether to open SSL
    public $ssl = true;
    // Load message last time (min)
    private $mintime;
    // Database connection
    private $sqllite;
    // configuration file
    private $conf;
    // 获取用户的令牌
    private $usertoken;
    // 配置文件地址
    private $conf_file = ROOT . '/data/conf.json';

    // Cookie file name
    private $cookie_file = ROOT . '/data/cookie.ini';
    // 令牌缓存文件
    private $tmp_file = ROOT . '/data/tmp.php';

    /**
     * [__construct description]
     * @Author    ZiShang520@gmail.com
     * @DateTime  2016-09-19T12:14:55+0800
     * @copyright (c)                      ZiShang520 All           Rights Reserved
     * @param     integer                  $mintime   [Load message last time (min)]
     */
    public function __construct($mintime = 10)
    {
        //
        $this->sqllite = new SQLite3(ROOT . '/data/agent.db', SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        // 获取基础配置信息
        $this->conf = $this->get_conf();
        // 设置标准header
        $this->header = [
            'Cache-Control' => 'Cache-control: no-cache, no-store',
            'User-Agent' => 'User-Agent: ' . $this->conf['UA'],
            'Upgrade-Insecure-Requests' => 'Upgrade-Insecure-Requests: 1',
            'Accept' => 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'Accept-Language: zh-CN,zh;q=0.8,en;q=0.6',
            'Dnt' => 'Dnt: 1',
            'Accept-encoding' => 'Accept-encoding: gzip'
        ];
        $this->usertoken = $this->get_usertoken();
        // 设置csrf
        $this->header['X-CSRFToken'] = 'X-CSRFToken: ' . $this->usertoken['token'];
        $this->mintime = $mintime;
    }

    /**
     * [curl description]
     * @Author    ZiShang520@gmail.com
     * @DateTime  2016-09-19T12:15:19+0800
     * @copyright (c)                      ZiShang520 All           Rights Reserved
     * @param     [type]                   $url       [request url]
     * @param     [type]                   $post      [post data]
     * @param     array                    $header    [header]
     * @param     boolean                  $cookie    [auto save cookie]
     * @return    [type]                              [curl exec data]
     */
    protected function curl($url, $post = null, $header = [], $cookie = true)
    {
        $header = array_merge($this->header, $header);
        $data = [];
        $ch = curl_init();
        curl_setopt_array($ch,
            [
                CURLOPT_URL => $url, //Request Url
                // CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5,
                // CURLOPT_PROXY => '127.0.0.1:1080', // Please Set CURLOPT_SSL_VERIFYPEER => false
                CURLOPT_HTTPHEADER => $header, //Set Request Header
                CURLOPT_AUTOREFERER => true, // Open Auto Referer
                CURLOPT_FOLLOWLOCATION => true, //Open Auto Location
                // CURLOPT_COOKIE => $this->conf['cookie'], //Set Cookie
                CURLOPT_TIMEOUT => 30, //Set Timeout
                CURLOPT_RETURNTRANSFER => true, //Set Not Show Response Headers
                CURLOPT_HEADER => false, //Set Not Output Header
                CURLOPT_NOBODY => false, //Set Not Show Body
                CURLOPT_ENCODING => "gzip" // Set Accept-encoding
            ]
        );
        if ($post !== null) {
            $post = is_array($post) ? http_build_query($post) : $post;
            curl_setopt_array($ch,
                [
                    CURLOPT_POST => true, //Post request
                    CURLOPT_POSTFIELDS => $post,
                ]
            );
        }
        if ($this->ssl) {
            // Check whether the domain name is set in the certificate, and whether the match is provided with the host name.
            curl_setopt_array($ch,
                [
                    CURLOPT_SSL_VERIFYPEER => true, // Only trust the certificate issued by CA
                    CURLOPT_CAINFO => ROOT . '/data/cacert.pem', // CA root certificate (used to verify whether the site certificate is issued by the CA)
                    CURLOPT_SSL_VERIFYHOST => 2,
                ]
            );
        }
        if ($cookie) {
            curl_setopt_array($ch,
                [
                    CURLOPT_COOKIEJAR => $this->cookie_file, //Storing cookie information
                    CURLOPT_COOKIEFILE => $this->cookie_file, // use cookie
                ]
            );
        }
        $data['info'] = curl_exec($ch);
        $data['header'] = curl_getinfo($ch);
        $data['status'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $data;
    }

    // 获取基础配置文件
    protected function get_conf()
    {
        file_exists($this->conf_file) || $this->ShowError('conf.json Not Found');
        $conf = JsObj::decode(file_get_contents($this->conf_file), true);
        if (empty($conf['email'])) {
            $this->ShowError('Conf.json Email Not Set');
        }
        if (empty($conf['password'])) {
            $this->ShowError('Conf.json Password Not Set');
        }
        if (empty($conf['UA'])) {
            $this->ShowError('Conf.json UA Not Set');
        }
        if (empty($conf['minLatE6'])) {
            $this->ShowError('Conf.json minLatE6 Not Set');
        }
        if (empty($conf['minLngE6'])) {
            $this->ShowError('Conf.json minLngE6" Not Set');
        }
        if (empty($conf['maxLatE6'])) {
            $this->ShowError('Conf.json maxLatE6 Not Set');
        }
        if (empty($conf['maxLngE6'])) {
            $this->ShowError('Conf.json maxLngE6 Not Set');
        }
        if (empty($conf['latE6'])) {
            $this->ShowError('Conf.json latE6 Not Set');
        }
        if (empty($conf['lngE6'])) {
            $this->ShowError('Conf.json lngE6 Not Set');
        }
        return $conf;
    }

    public function get_usertoken()
    {
        $tmp = new Config($this->tmp_file);
        if ($tmp->get('time') && my_date_diff($tmp->get('time')) >= 0) {
            return $tmp->get();
        }
        if (!$v = $this->get_v()) {
            $this->ShowError('Get v Error');
        }
        if (!$token = $this->get_token()) {
            $this->ShowError('Get CsrfToken Error');
        }
        $tmp->set(['v' => $v, 'token' => $token, 'time' => date('Y-m-d')]);
        if ($tmp->save()) {
            return $tmp->get();
        }
        $this->ShowError('Save Token File Error');
    }

    // 获取v值
    public function get_v()
    {
        $url = 'https://www.ingress.com/intel';
        $info = $this->curl($url, null);
        if ($info['status'] != 200) {
            return false;
        }
        if (preg_match('/<a\shref="(.*?)"\s.*?>Sign\sin<\/a>/sim', $info['info'], $match)) {
            if (count($match) != 2) {
                return false;
            }
            if (!$this->auto_login($match[1])) {
                return false;
            }
            $info = $this->curl($url, null);
            if ($info['status'] != 200) {
                return false;
            }
        }
        if (!preg_match('/<script\stype="text\/javascript"\ssrc="\/jsc\/gen_dashboard_(\w+)\.js"><\/script>/sim', $info['info'], $match)) {
            return false;
        }
        if (count($match) != 2) {
            return false;
        }
        return $match[1];
    }

    // 判断是否需要登录
    protected function check_login($body)
    {
        return !(preg_match('/(login|登录)/sim', $body));
    }

    // 自动登录
    protected function auto_login($login_url)
    {
        // 载入对象
        $html = new simple_html_dom();

        $header['Referer'] = 'Referer: https://www.ingress.com/intel';
        $_info = $this->curl($login_url, null, $header);
        if ($_info['status'] != 200) {
            $this->ShowError('Get Login Url Error');
            return false;
        }
        // jump google login
        if ($this->check_login($_info['info'])) {
            return true;
        }
        // 获取跳转
        $html->load($_info['info']);
        $refresh = $html->find('meta[http-equiv="refresh"]');
        // 备用
        $url = 'https://accounts.google.com/ServiceLogin?continue=https%3A%2F%2Fappengine.google.com%2F_ah%2Fconflogin%3Fcontinue%3Dhttps%3A%2F%2Fwww.ingress.com%2Fintel&rip=1&nojavascript=1&service=ah&ltmpl=gm';
        foreach ($refresh as $key => $value) {
            preg_match('/(?<=url\=).+/sim', $value->content, $match);
            $url = htmlspecialchars_decode($match[0]);
        }
        $info = $this->curl($url);
        if ($info['status'] != 200) {
            $this->ShowError('Jump Login Error');
            return false;
        }
        // 抓取输入账号页面页面
        // 解析dom
        $html->load($info['info']);
        $main = $html->find('form input[name]');
        $data = [
            'Email' => $this->conf['email'],
        ];
        foreach ($main as $value) {
            switch ($value->name) {
                case 'Page':
                    $data['Page'] = $value->value;
                    break;
                case 'GALX':
                    $data['GALX'] = $value->value;
                    break;
                case 'gxf':
                    $data['gxf'] = $value->value;
                    break;
                case 'continue':
                    $data['continue'] = $value->value;
                    break;
                case 'service':
                    $data['service'] = $value->value;
                    break;
                case 'ltmpl':
                    $data['ltmpl'] = $value->value;
                    break;
                case 'rip':
                    $data['rip'] = $value->value;
                    break;
                case 'ProfileInformation':
                    $data['ProfileInformation'] = $value->value;
                    break;
                case 'SessionState':
                    $data['SessionState'] = $value->value;
                    break;
                case '_utf8':
                    $data['_utf8'] = $value->value;
                    break;
                case 'bgresponse':
                    $data['bgresponse'] = $value->value;
                    break;
                case 'identifiertoken':
                    $data['identifiertoken'] = $value->value;
                    break;
                case 'identifiertoken_audio':
                    $data['identifiertoken_audio'] = $value->value;
                    break;
                case 'identifier-captcha-input':
                    $data['identifier-captcha-input'] = $value->value;
                    break;
                case 'signIn':
                    $data['signIn'] = $value->value;
                    break;
                case 'Passwd':
                    $data['Passwd'] = $value->value;
                    break;
                case 'PersistentCookie':
                    $data['PersistentCookie'] = $value->value;
                    break;
                case 'rmShown':
                    $data['rmShown'] = $value->value;
                    break;
            }
        }
        $form = $html->find('form[action]');
        // 备用
        $username_xhr_url = 'https://accounts.google.com/signin/v1/lookup';
        foreach ($form as $key => $value) {
            $username_xhr_url = $value->action;
        }
        $header['Referer'] = 'Referer: ' . $info['header']['url'];
        $_ = $this->curl($username_xhr_url, $data, $header);
        if ($_['status'] != 200) {
            $this->ShowError('Check User Email Error');
            return false;
        }
        // 登录
        $html->load($_['info']);
        $login_page = $html->find('form input[name]');
        $login_data = [
            'Email' => $this->conf['email'],
            'Passwd' => $this->conf['password'],
        ];
        foreach ($login_page as $value) {
            switch ($value->name) {
                case 'Page':
                    $login_data['Page'] = $value->value;
                    break;
                case 'GALX':
                    $login_data['GALX'] = $value->value;
                    break;
                case 'gxf':
                    $login_data['gxf'] = $value->value;
                    break;
                case 'continue':
                    $login_data['continue'] = $value->value;
                    break;
                case 'service':
                    $login_data['service'] = $value->value;
                    break;
                case 'ltmpl':
                    $login_data['ltmpl'] = $value->value;
                    break;
                case 'rip':
                    $login_data['rip'] = $value->value;
                    break;
                case 'ProfileInformation':
                    $login_data['ProfileInformation'] = $value->value;
                    break;
                case 'SessionState':
                    $login_data['SessionState'] = $value->value;
                    break;
                case '_utf8':
                    $login_data['_utf8'] = $value->value;
                    break;
                case 'bgresponse':
                    $login_data['bgresponse'] = $value->value;
                    break;
                case 'signIn':
                    $login_data['signIn'] = $value->value;
                    break;
                case 'PersistentCookie':
                    $login_data['PersistentCookie'] = $value->value;
                    break;
                case 'rmShown':
                    $login_data['rmShown'] = $value->value;
                    break;
            }
        }
        $form = $html->find('form[action]');
        // 备用
        $password_url = 'https://accounts.google.com/signin/challenge/sl/password';
        foreach ($form as $key => $value) {
            $password_url = $value->action;
        }
        $header['Referer'] = 'Referer: ' . $_['header']['url'];
        $info = $this->curl($password_url, $login_data, $header);
        if ($this->check_login($info['info'])) {
            return true;
        }
        $this->ShowError('Login Error');
        return false;
    }

    protected function get_token()
    {
        if (is_file($this->cookie_file)) {
            if (preg_match('/(?<=csrftoken[\s*])\w+(?=\n)?/sim', file_get_contents($this->cookie_file), $matchs)) {
                return $matchs[0];
            }
        }
        return false;
    }
    //Get message
    public function get_msg()
    {
        $url = 'https://www.ingress.com/r/getPlexts';
        $header['content-type'] = 'content-type: application/json; charset=UTF-8';
        $header['Origin'] = 'Origin: https://www.ingress.com';
        $header['Referer'] = 'Referer: https://www.ingress.com/intel';
        $data = '{"minLatE6":' . $this->conf['minLatE6'] . ',"minLngE6":' . $this->conf['minLngE6'] . ',"maxLatE6":' . $this->conf['maxLatE6'] . ',"maxLngE6":' . $this->conf['maxLngE6'] . ',"minTimestampMs":' . strval(bcsub(microtime(true) * 1000, 60000 * $this->mintime)) . ',"maxTimestampMs":-1,"tab":"faction","ascendingTimestampOrder":true,"v":"' . $this->usertoken['v'] . '"}';
        $info = $this->curl($url, $data, $header);
        if ($info['status'] != 200) {
            return false;
        }
        return $info['info'];
    }

    //send message
    public function send_msg($msg)
    {
        $url = 'https://www.ingress.com/r/sendPlext';
        $header['content-type'] = 'content-type: application/json; charset=UTF-8';
        $header['Origin'] = 'Origin: https://www.ingress.com';
        $header['Referer'] = 'Referer: https://www.ingress.com/intel';
        $data = '{"message":"' . $msg . '","latE6":' . $this->conf['latE6'] . ',"lngE6":' . $this->conf['lngE6'] . ',"tab":"faction","v":"' . $this->usertoken['v'] . '"}';
        $info = $this->curl($url, $data, $header);
        if ($info['status'] != 200) {
            return false;
        }
        $arr = json_decode($info['info'], true);
        if (empty($arr['result'])) {
            return false;
        } else {
            return $arr['result'];
        }
    }

    // Passcode automatic exchange
    public function auto_passcode($code)
    {
        $url = 'https://www.ingress.com/r/redeemReward';
        $header['content-type'] = 'content-type: application/json; charset=UTF-8';
        $header['Origin'] = 'Origin: https://www.ingress.com';
        $header['Referer'] = 'Referer: https://www.ingress.com/intel';
        $data = '{"passcode":"' . $code . '","v":"' . $this->usertoken['v'] . '"}';
        $info = $this->curl($url, $data, $header);
        if ($info['status'] != 200) {
            return false;
        }
        return json_decode($info['info'], true);
    }

    //给萌新发消息
    public function auto_send_msg_new_agent()
    {
        $msg = $this->get_msg();
        if (!$msg) {
            return 'get message error';
        }
        if (!$arr = json_decode($msg, true)) {
            return 'message decode error';
        }
        if (empty($arr['result']) || !is_array($arr['result'])) {
            return 'not new message';
        }
        $newagents = [];
        foreach ($arr['result'] as $value) {
            if ($agent = $this->check_new_agent($value[2]['plext']['text'])) {
                array_push($newagents, $agent);
            }
        }
        if (count($newagents) == 0) {
            return 'not new agent';
        }
        $newagents = array_unique($newagents);
        $agents = '';
        $values = [];
        foreach ($newagents as $value) {
            array_push($values, '("' . $value . '",' . time() . ')');
            $agents .= '@' . $value . ' ';
        }
        if (($this->send_msg($agents . ' ' . $this->rand_msg())) != 'success') {
            return 'message send error';
        }
        $sql = 'INSERT INTO `user` (`agent`, `createtime`) VALUES ' . implode(', ', $values);
        if ($this->sqllite->exec($sql)) {
            return 'message send success,Info storage success';
        } else {
            return 'message send success,Info storage error';
        }
    }

    //是不是萌新
    private function check_new_agent($msg = '')
    {
        $match = '';
        if (preg_match('/\[secure\]\s+(\w+):\s+has\scompleted\straining\./sim', $msg, $match)) {
            if (count($match) != 2) {
                return false;
            }
        } else if (preg_match('/\[secure\]\s(\w+):\s+.*/sim', $msg, $match)) {
            if (count($match) != 2) {
                return false;
            }
            if (!preg_match('/(' . $this->regexp() . ')/sim', $match[0])) {
                return false;
            }
        } else {
            return false;
        }
        $sql = 'SELECT COUNT(`id`) FROM `user` WHERE `agent`="' . $match[1] . '"';
        if ($this->sqllite->querySingle($sql) > 0) {
            return false;
        }
        return $match[1];
    }
    //随机消息
    private function rand_msg()
    {
        if (empty($this->conf['rand_msg'])) {
            $data = array(
                ' 欢迎新人，快来加入川渝蓝军群(群号126821831)，发现精彩内容。',
                ' 欢迎选择加入抵抗军·川渝蓝军群(群号126821831)，一起为建设社会主义社会、实现人类的全面自由发展而奋斗吧。',
                ' 您已进入秋名山路段，此处常有老司机出没，加入川渝蓝军群(群号126821831)，寻找这里的老司机吧。',
                ' 欢迎加入熊猫抵抗军(群号126821831)，感谢你在与shapers的斗争中选择了人性与救赎，选择与死磕并肩同行。新人你好，我是死磕。',
                ' ingrees亚洲 中国分区 川渝地区组织需要你！快来加入川渝蓝军群(群号126821831)。',
            );
        } else {
            $data = $this->conf['rand_msg'];
        }
        return $data[rand(0, count($data) - 1)];
    }
    // 加入关键词匹配
    private function regexp()
    {
        if (empty($this->conf['regexp'])) {
            $data = '大家好|我是萌新|新人求带|新人求罩|大佬们求带|求组织|带带我';
        } else {
            $data = implode('|', $this->conf['regexp']);
        }
        return $data;
    }

    public function ShowError($msg)
    {
        throw new Exception($msg);
    }

    //析构函数
    public function __destruct()
    {
        $this->sqllite->close();
    }
}
