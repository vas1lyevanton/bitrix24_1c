<?php
	
	//define('C_REST_IGNORE_SSL',true);
	//define('C_REST_CLIENT_ID','local.5c891bca57c837.33309782');
	//define('C_REST_CLIENT_SECRET','jN7UeGI5G4K2xPnfcSDt83xoDx4NHmo2n1FvPFVn0GIR3ja4Pr');

	//define('C_REST_WEB_HOOK_URL','https://rest.bitrix24.ru/rest/1/doutwasdasdmgc1/');

	
	/**
	 *  define:
	 *      C_REST_WEB_HOOK_URL = 'https://rest.bitrix24.ru/rest/1/doutwasdasdmgc1/'  url on creat Webhook
	 *      or
	 *      C_REST_CLIENT_ID = '' //Application ID
	 *      C_REST_CLIENT_SECRET = ''//Application key
	 *
	 *
	 *      C_REST_BLOCK_LOG = true //turn off default logs
	 *      C_REST_LOGS_DIR = __DIR__ .'/logs/' //directory path to save the log
	 *      C_REST_IGNORE_SSL = true //turn off validate ssl by curl
	 */
	class CRest{
		const BATCH_COUNT = 50;//count batch 1 query
		const TYPE_TRANSPORT = 'json';// json or xml
		
		/**
		 * @var $arParams array
		 * $arParams = [
		 *      'method'    => 'some rest method',
		 *      'params'    => []//array params of method
		 * ];
		 * @return mixed array|string|boolean curl-return or error
		 *
		 */
		protected static function curlPost( $arParams ){
			if(!function_exists('curl_init')){
				return [
					'error'             => 'error_php_lib_curl',
					'error_information' => 'need install curl lib'//todo: ссылка как установить курл
				];
			}
			$arSettings = static::getAppSettings();
			if($arSettings !== false) {
				if($arParams['this_auth'] == 'Y'){
					$url =  'https://oauth.bitrix.info/oauth/token/';
				}else{
					$url = $arSettings["client_endpoint"].$arParams['method'].'.'.static::TYPE_TRANSPORT;
					if(empty($arSettings['is_web_hook']) || $arSettings['is_web_hook'] != 'Y'){
						$arParams['params']['auth'] = $arSettings['access_token'];
					}
				}
				$sPostFields = http_build_query($arParams['params']);
				try{
					$obCurl = curl_init();
					curl_setopt( $obCurl, CURLOPT_URL, $url );
					curl_setopt( $obCurl, CURLOPT_RETURNTRANSFER, true );
					//curl_setopt( $obCurl, CURLOPT_HEADER, true );
					//curl_setopt( $obCurl, CURLINFO_HEADER_OUT, true );
					if( $sPostFields ){
						curl_setopt( $obCurl, CURLOPT_POST, true );
						curl_setopt( $obCurl, CURLOPT_POSTFIELDS, $sPostFields );
					}
					curl_setopt( $obCurl, CURLOPT_FOLLOWLOCATION, (isset($arParams['followlocation']))?$arParams['followlocation']:1 );
					if(defined("C_REST_IGNORE_SSL") && C_REST_IGNORE_SSL === true) {
						curl_setopt($obCurl,CURLOPT_SSL_VERIFYPEER, false);
						curl_setopt($obCurl,CURLOPT_SSL_VERIFYHOST, false);
					}
					$out = curl_exec( $obCurl );
					$info = curl_getinfo($obCurl);
					if (curl_errno($obCurl)) {
						$info['curl_error'] = curl_error($obCurl);
					}
					if(static::TYPE_TRANSPORT == 'xml'){
						$result = $out;
					}else{
						$result = json_decode($out , true);
					}
					curl_close( $obCurl );

					if($result['error'] == 'expired_token'	&& empty($arParams['this_auth'])){
						$result = static::GetNewAuth($arParams);
					}elseif($result['error'] == 'invalid_token'){
						$result['error_information'] = 'invalid token, need reinstall application';
					}elseif($result['error'] == 'invalid_grant'){
						$result['error_information'] = 'invalid grant, check out define C_REST_CLIENT_SECRET or C_REST_CLIENT_ID';
					}elseif($result['error'] == 'invalid_client'){
						$result['error_information'] = 'invalid client, check out define C_REST_CLIENT_SECRET or C_REST_CLIENT_ID';
					}elseif($result['error'] == 'QUERY_LIMIT_EXCEEDED'){
						$result['error_information'] = 'Too many requests, maximum 2 query by second';
					}
					
					static::setLog(
						[
							'url'=>$url,
							'info' => $info,
							'params'=>$arParams,
							'result'=>$result
						],
						'curlPost'
					);
					
					return $result;
				}catch( Exception $e ){
					return [
						'error'             =>  'exception',
						'error_information' =>  $e->getMessage(),
					];
				}
			}
			return [
				'error'             =>  'no_install_app',
				'error_information' =>  'error install app, pls install local application more info: '//todo: ссыдлку на доку по установке приложения
			];
		}
		
		/**
		 * Generate a request for curlPost()
		 * @var $method string
		 * @var $params array method params
		 * @return mixed array|string|boolean curl-return or error
		 */
		public static function get($method, $params = []){
			$arPost = [
				'method' => $method,
				'params' => $params
			];
			$result = static::curlPost($arPost);
			return $result;
		}
		
		/**
		 * example $arData:
		 * $arData = [
		 *      'find_contact' => [
		 *          'method' => 'crm.duplicate.findbycomm',
		 *          'params' => [ "entity_type" => "CONTACT",  "type" => "PHONE", "values" => array("+79625011243") ]
		 *      ],
		 *      'get_contact' => [
		 *          'method' => 'crm.contact.get',
		 *          'params' => [ "id" => '$result[find_contact][CONTACT][0]' ]
		 *      ],
		 *      'get_company' => [
		 *          'method' => 'crm.company.get',
		 *          'params' => [ "id" => '$result[get_contact][COMPANY_ID]', "select" => ["*"],]
		 *      ]
		 * ];
		 * @var $arData array
		 * @var $halt integer 0 or 1 stop batch on error
		 * @return array
		 *
		 */
		public static function getBatch($arData,$halt = 0){
			$arResult = [];
			if(is_array($arData)){
				$arDataRest = [];
				$i = 0;
				foreach($arData as $key=>$data){
					if(!empty($data['method'])){
						$i++;
						if(static::BATCH_COUNT > $i){
							$arDataRest['cmd'][$key] = $data['method'];
							if(!empty($data['params'])){
								$arDataRest['cmd'][$key] .= '?'.http_build_query($data['params']);
							}
						}
					}
				}
				if(!empty($arDataRest)){
					$arDataRest['halt'] = $halt;
					$arResult = static::get('batch',$arDataRest);
				}
			}
			return $arResult;
		}
		/**
		 * call where install application even url
		 * only for rest application, not webhook
		 */
		public static function installApp(){
			if($_REQUEST['event'] == 'ONAPPINSTALL' && !empty($_REQUEST['auth'])){
				static::setAppSettings($_REQUEST['auth'], true);
			}
			static::setLog(
				$_REQUEST,
				'installApp'
			);
			return true;
		}
		/**
		 * Getting a new authorization and sending a request for the 2nd time
		 * @var $arParams array request when authorization error returned
		 * @return array query result from $arParams
		 *
		 */
		private static function  GetNewAuth($arParams){
			$result = [];
			$arSettings = static::getAppSettings();
			if($arSettings !== false){
				$arParamsAuth = [
					'this_auth'=>'Y',
					'params' =>
						[
							'client_id'     => $arSettings['C_REST_CLIENT_ID'],
							'grant_type'    => 'refresh_token',
							'client_secret' => $arSettings['C_REST_CLIENT_SECRET'],
							//'redirect_uri'  => 'https://site.local/callback.php',
							'refresh_token' => $arSettings["refresh_token"],
						]
				];
				$newData = static::curlPost($arParamsAuth);
				if(isset($newData['C_REST_CLIENT_ID'])){
					unset($newData['C_REST_CLIENT_ID']);
				}
				if(isset($newData['C_REST_CLIENT_SECRET'])){
					unset($newData['C_REST_CLIENT_SECRET']);
				}
				if(isset($newData['error'])){
					unset($newData['error']);
				}
				if(static::setAppSettings($newData)){
					$arParams['this_auth'] = 'N';
					$result = static::curlPost($arParams);
				}
			}
			return $result;
		}
		
		/**
		 * @var $arSettings array settings application
		 * @var $isInstall boolean true if install app by installApp()
		 * @return boolean
		 */
		private static function setAppSettings($arSettings,$isInstall = false){
			$return = false;
			if(is_array($arSettings)){
				$oldData = static::getAppSettings();
				if($isInstall != true && !empty($oldData) && is_array($oldData) ){
					$arSettings = array_merge($oldData, $arSettings);
				}
				$return = static ::setSettingData($arSettings);
			}
			return $return;
		}
		/**
		 * @return array setting application for query
		 */
		private static function getAppSettings(){
			if(defined("C_REST_WEB_HOOK_URL") && !empty(C_REST_WEB_HOOK_URL)){
				$arData = [
					'client_endpoint' => C_REST_WEB_HOOK_URL,
					'is_web_hook' => 'Y'
				];
				$isCurrData = true;
			}else{
				$arData =  static::getSettingData();
				$isCurrData = false;
				if(
					!empty($arData['access_token']) &&
					!empty($arData['domain']) &&
					!empty($arData['refresh_token']) &&
					!empty($arData['application_token'])
				){
					$isCurrData = true;
				}
			}
			return ($isCurrData)?$arData:false;
		}
		/**
		 * Can extend this method to change the data storage location.
		 * @return array setting for getAppSettings()
		 */
		protected static function getSettingData(){
			$return = json_decode(file_get_contents(__DIR__.'/settings.json'),true);
			$return['C_REST_CLIENT_ID'] = C_REST_CLIENT_ID;
			$return['C_REST_CLIENT_SECRET'] = C_REST_CLIENT_SECRET;
			return $return;
		}
		/**
		 * Can extend this method to change the data storage location.
		 * @return boolean is successes save data for setSettingData()
		 */
		protected static function setSettingData($arSettings){
			return file_put_contents(__DIR__.'/settings.json',json_encode($arSettings));
		}
		/**
		 * Can extend this method to change the data storage location.
		 * @return boolean is successes save data for setSettingData()
		 */
		public static function setLog($arData, $type = ''){
			$return = false;
			if(!defined("C_REST_BLOCK_LOG") || C_REST_BLOCK_LOG !== true) {
				if(defined("C_REST_LOGS_DIR")) {
					$path = C_REST_LOGS_DIR;
				}else{
					$path = __DIR__ . '/logs/';
				}
				$path .=  date("Y-m-d/H") . '/';
				mkdir($path, 0775, true);
				$return = file_put_contents($path.$type.'_'.time().'_'.rand(1,999999).'log.json',json_encode($arData));
			}
			return $return;
		}
		
	}
	
	