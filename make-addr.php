<?php
//在命令行下运行，直接生成dnsmasq的去广告用途的配置文件
//2017年12月31日

set_time_limit(60);

if(PHP_SAPI != 'cli'){
	die('nothing.');
}

$arr_blacklist = require('./black_domain_list.php');


$arr_result = array();

echo '开始下载host1....',"\n";
$host1 = makeAddr::http_get('https://raw.githubusercontent.com/vokins/yhosts/master/dnsmasq/union.conf');

$arr_result = makeAddr::get_domain_list($host1);
echo '开始下载host2....',"\n";
$host2 = makeAddr::http_get('https://raw.githubusercontent.com/vokins/yhosts/master/hosts.txt');

$arr_result = array_merge_recursive($arr_result, makeAddr::get_domain_list($host2));

echo '开始下载host3....',"\n";
$host3 = makeAddr::http_get('http://www.malwaredomainlist.com/hostslist/hosts.txt');

$arr_result = array_merge_recursive($arr_result, makeAddr::get_domain_list($host3));

echo '写入文件大小：';
var_dump(makeAddr::write_to_conf($arr_result, './adblock-for-dnsmasq.conf'));




class makeAddr{


	public static function http_get($url){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPGET, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	// 	curl_setopt($ch, CURLOPT_POSTFIELDS, $str);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'T_T angent 2.0.5/' . phpversion());
		$result = curl_exec($ch);
		$errno = curl_errno($ch);
		curl_close($ch);
	
		return $result;
	}

	public static function extract_main_domain($str_domain){
		if(empty($str_domain)){
			return "";
		}

		$str_reg = '/^[a-z0-9\-\.]*?([a-z0-9\-]+(\.com|\.cn|\.net|\.org|\.cn|\.me|\.co|\.info|\.cc|\.tv';
		$str_reg .= '|\.pw|\.biz|\.top|\.win|\.bid|\.cf|\.club|\.ne|\.de|\.la|\.us|\.mobi|\.hn|\.asia';
		$str_reg .= '|\.jp|\.tw|\.am|\.hk|\.site|\.live|\.xyz|\.space|\.fr|\.es|\.nl|\.au|\.in|\.ru';
		$str_reg .= '|\.su|\.world|\.io|\.trade|\.bet|\.im|\.fm|\.today|\.wang|\.rocks|\.vip|\.eu|\.run';
		$str_reg .= '|\.online|\.website|\.cricket|\.date|\.men|\.ca|\.xxx|\.name|\.pl|\.be|\.il|\.gov|\.it';
		$str_reg .= '|\.cl|\.tk|\.cz|\.hu|\.ro|\.vg|\.ws|\.nu|\.vn|\.lt|\.edu|\.lv|\.mx|\.by|\.gr|\.br|\.fi';
		$str_reg .= '|\.pt|\.dk|\.se|\.at|\.id|\.ve|\.ir|\.ma|\.ch|\.nf|\.bg|\.ua|\.is|\.hr|\.shop|\.xin|\.si|\.or';
		$str_reg .= '|\.sk|\.kz';
		$str_reg .= ')';

		$str_reg .= '(\.cn|\.tw|\.uk|\.jp|\.kr|\.th|\.au|\.ua|\.so|\.br|\.sg|\.pt|\.ec|\.ar|\.my|\.tr|\.bd|\.mk)?)$/';
		preg_match($str_reg, $str_domain,$matchs);

		return strval($matchs[1]);

	}
	
	public static function get_domain_list($str_hosts){
		$strlen = strlen($str_hosts);
		if($strlen < 10){
			return array();
		}

		$str_hosts = $str_hosts . "\n"; //防止最后一行没有换行符

		$i=0;
		$arr_domains = array();
		while($i < $strlen){
			$end_pos = strpos($str_hosts, "\n", $i);
			$line = trim(substr($str_hosts, $i, $end_pos - $i));
			$i = $end_pos+1;
			if(empty($line) || ($line{0} == '#')){//注释行忽略
				continue;
			}
			$line = strtolower(preg_replace('/[\s\t]+/', "/", $line));

			if((strpos($line, '127.0.0.1') === false) && (strpos($line, '0.0.0.0') === false)){
				continue;
			}
		
			$row = explode('/', $line);
			if(strpos($row[1], '.') === false){
				continue;
			}
			
			$arr_domains[self::extract_main_domain($row[1])][] = $row[1];
		}

		$arr_domains = array_merge($arr_domains, $GLOBALS['arr_blacklist']);
		return $arr_domains;
	}

	public static function write_to_conf($arr_result, $str_file){

		$fp = fopen($str_file, 'w');
		$write_len = 0;
		foreach($arr_result as $rk => $rv){
			if(array_key_exists($rk, $GLOBALS['arr_blacklist'])){//黑名单操作
				foreach($GLOBALS['arr_blacklist'][$rk] as $bv){
					$write_len += fwrite($fp, 'address=/' . $bv . '/127.0.0.1' . "\n");
				}
				continue;
			}

			if(empty($rk)){//遗漏的域名，不会写入到最终的配置里
				print_r($rv);
				continue;
			}
			if(!is_array($rv)){
				$write_len += fwrite($fp, 'address=/' . $rv . '/127.0.0.1' . "\n");
				continue;
			}

			array_unique($rv);

			$rk_found = false;
			if(in_array('.' . $rk, $rv)){
				$write_len += fwrite($fp, 'address=/.' . $rk . '/127.0.0.1' . "\n");
				$rk_found = true;
			}

			foreach($rv as $rvv){
				if(!$rk_found || (strpos($rvv, '.' . $rk) === false)){
					$write_len += fwrite($fp, 'address=/' . $rvv . '/127.0.0.1' . "\n");
				}
			}
		}

		fclose($fp);

		return $write_len;
	}
}





























