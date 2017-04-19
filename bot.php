<?php

define('BOT_TOKEN', '300000000:AAAAAAAAAAAAAAAAAAAAAAAAA');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');
define('SITE_ADDR','https://www.yourbot.ru/');//
define('WEBHOOK_URL', 'https://www.yourbot.ru/bot.php');
define('FILE_LIMIT', 200); //MB

function apiRequestWebhook($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  $parameters["method"] = $method;

  header("Content-Type: application/json");
  echo json_encode($parameters);
  return true;
}

function exec_curl_request($handle) {
  $response = curl_exec($handle);

  if ($response === false) {
    $errno = curl_errno($handle);
    $error = curl_error($handle);
    error_log("Curl returned error $errno: $error\n");
    curl_close($handle);
    return false;
  }

  $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
  curl_close($handle);

  if ($http_code >= 500) {
    // do not wat to DDOS server if something goes wrong
    sleep(10);
    return false;
  } else if ($http_code != 200) {
    $response = json_decode($response, true);
    error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
    if ($http_code == 401) {
      throw new Exception('Invalid access token provided');
    }
    return false;
  } else {
    $response = json_decode($response, true);
    if (isset($response['description'])) {
      error_log("Request was successfull: {$response['description']}\n");
    }
    $response = $response['result'];
  }

  return $response;
}

function apiRequest($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  foreach ($parameters as $key => &$val) {
    // encoding to JSON array parameters, for example reply_markup
    if (!is_numeric($val) && !is_string($val)) {
      $val = json_encode($val);
    }
  }
  $url = API_URL.$method.'?'.http_build_query($parameters);

  $handle = curl_init($url);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);

  return exec_curl_request($handle);
}

function apiRequestJson($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  $parameters["method"] = $method;

  $handle = curl_init(API_URL);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);
  curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
  curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

  return exec_curl_request($handle);
}

function processMessage($message) {
  // process incoming message
  $message_id = $message['message_id'];
  $chat_id = $message['chat']['id'];
  if (isset($message['text'])) {
    // incoming text message
    $text = $message['text'];

    if (strpos($text, "/start") === 0) {
      apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Hello', 'reply_markup' => array(
        'keyboard' => array(array('/help', '/clr')),
        'one_time_keyboard' => true,
        'resize_keyboard' => true)));
    }else if ($text === "/clr") {
		
			foreach (glob('./*.zip') as $file)
			{
				unlink($file);		
				apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => $file));	
			}
			apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => "Clearned"));	
			
		
	}else if (stripos($text, 'http')!==false  ) {
		
		# Get all header information
		$data = get_headers($text, true);
		# Look up validity
		if (isset($data['Content-Length']))
		{
			$fdsize=round($data['Content-Length']/1024/1024,0);
			if($fdsize>FILE_LIMIT)
			{
				apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => "Ваш  файл $fdsize МБ   разрешено не более $filelimit "));
				die("0");
			}
			
		}
			
	  
		
		
		$tfile= "tmp".md5(date("Y-m-d H:i:s")). md5($text). '.zip';
		if(!@copy($text,__DIR__ ."/".$tfile))
		{
			$errors= error_get_last();
			apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => "ERROR: ".$errors['type']."_".$errors['message']));	 
		} else {
			
			$size=round(filesize(__DIR__ ."/".$tfile)/1024/1024,0);
			//apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => $size));
			if($size<=19)
			{
				apiRequest("sendDocument", array('chat_id' => $chat_id,'document'=>SITE_ADDR.$tfile,'caption' => end(explode(".", $tfile)) ));
				unlink( __DIR__ ."/".$tfile);
			}else
			{
				apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => SITE_ADDR.$tfile . "     ".$size));	
			}
			
		}
				
			
	}else if ($text === "/help") {
      apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Отправьте мне ссылку на файл, в зависимости от размера я пришлю вам файл или ссылку на него с расширением .zip'));
    } else if (strpos($text, "/stop") === 0) {
      // stop now
    } else {
      apiRequestWebhook("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => 'Cool send link with http:// or https://'));
    }
  } else {
    apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'I understand only text messages'));
  }
}

if (php_sapi_name() == 'cli') {
  // if run from console, set or delete webhook
  apiRequest('setWebhook', array('url' => isset($argv[1]) && $argv[1] == 'delete' ? '' : WEBHOOK_URL));
  exit;
}


$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
  // receive wrong update, must not happen
  exit;
}

if (isset($update["message"])) {
  processMessage($update["message"]);
}
