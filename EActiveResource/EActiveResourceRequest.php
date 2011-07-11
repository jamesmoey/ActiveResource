<?php
/**
 * @author Johannes "Haensel" Bauer, heavily influenced by Igor Ivanović who created the cUrl extension for Yii
 * @since 0.1
 * @version 0.1
 */

/**
 * This class is used to send and receive cURL requests to the service used by an ActiveResource.
 * It is heavily influenced by Igor Ivanović's cURL extension for Yii although a bit modified to allow sending PUT and DELETE
 * requests and different content/accept types etc.
 * @link: http://www.yiiframework.com/extension/curl
 */
class EActiveResourceRequest
{
        protected $uri;
        protected $ch;

	public $options = array();
	public $info = array();
    	public $error_code = 0;
    	public $error_string = '';

        const APPLICATION_JSON  ='application/json';
        const APPLICATION_XML   ='application/xml';
        const APPLICATION_FORM_URL_ENCODED  ='application/x-www-form-urlencoded';

        const METHOD_GET    = 'GET';
        const METHOD_POST   = 'POST';
        const METHOD_PUT    = 'PUT';
        const METHOD_DELETE = 'DELETE';

	protected $validOptions = array(
            'timeout'=>array('type'=>'integer'),
            'login'=>array('type'=>'array'),
            'proxy'=>array('type'=>'array'),
            'proxylogin'=>array('type'=>'array'),
            'setOptions'=>array('type'=>'array'),
	);

	/**
	 * Initialize the extension
	 * check to see if CURL is enabled and the format used is a valid one
	 */
	public function init()
        {
		if( !function_exists('curl_init') )
		throw new EActiveResourceRequestException( Yii::t('EActiveResourceRequest', 'You must have PHP curl enabled in order to use this extension.') );
	}

         /**
        * Setter
        * @set the option
        */
        protected function setOption($key,$value)
        {
            curl_setopt($this->ch,$key, $value);
        }


	/**
	* Formats Url if http:// dont exist
	* set http://
	*/
        public function setUri($uri)
        {
            if(!preg_match('!^\w+://! i', $uri))
            {
                $url = 'http://'.$uri;
            }
            $this->uri = $uri;
        }

	 /*
	* Set Url Cookie
	*/
        public function setCookies($values)
        {
            if (!is_array($values))
                throw new EActiveResourceRequestException(Yii::t('EActiveResourceRequest', 'Options must be an array'));
            else
                $params = $this->cleanPost($values);

            $this->setOption(CURLOPT_COOKIE, $params);
        }

	/*
	@LOGIN REQUEST
	sets login option
	If is not setted , return false
	*/
        public function setHttpLogin($username = '', $password = '')
        {
            $this->setOption(CURLOPT_USERPWD, $username.':'.$password);
        }
        /*
	@PROXY SETINGS
	sets proxy settings withouth username

	*/

        public function setProxy($url,$port = 80)
        {
            $this->setOption(CURLOPT_HTTPPROXYTUNNEL, TRUE);
            $this->setOption(CURLOPT_PROXY, $uri.':'.$port);
	}

	/*
	@PROXY LOGIN SETINGS
	sets proxy login settings calls onley if is proxy setted

	*/
	public function setProxyLogin($username = '', $password = '')
        {
            $this->setOption(CURLOPT_PROXYUSERPWD, $username.':'.$password);
        }
	/*
	@VALID OPTION CHECKER
	*/
	protected static function checkOptions($value, $validOptions)
        {
        if (!empty($validOptions))
        {
            foreach ($value as $key=>$val)
                {
                    if (!array_key_exists($key, $validOptions))
                    {
                        throw new EActiveResourceRequestException(Yii::t('EActiveResourceRequest', '{k} is not a valid option', array('{k}'=>$key)));
                    }
                    $type = gettype($val);
                    if ((!is_array($validOptions[$key]['type']) && ($type != $validOptions[$key]['type'])) || (is_array($validOptions[$key]['type']) && !in_array($type, $validOptions[$key]['type'])))
                    {
                        throw new EActiveResourceRequestException(Yii::t('EActiveResourceRequest', '{k} must be of type {t}',
                        array('{k}'=>$key,'{t}'=>$validOptions[$key]['type'])));
                    }

                    if (($type == 'array') && array_key_exists('elements', $validOptions[$key]))
                    {
                        self::checkOptions($val, $validOptions[$key]['elements']);
                    }
                }
            }
        }
        /*
	@DEFAULTS
	*/
        protected function setDefaults()
        {
            !isset($this->options['timeout'])  ?  $this->setOption(CURLOPT_TIMEOUT,30) : $this->setOption(CURLOPT_TIMEOUT,$this->options['timeout']);
            isset($this->options['setOptions'][CURLOPT_HEADER]) ? $this->setOption(CURLOPT_HEADER,$this->options['setOptions'][CURLOPT_HEADER]) : $this->setOption(CURLOPT_HEADER,FALSE);
            isset($this->options['setOptions'][CURLOPT_RETURNTRANSFER]) ? $this->setOption(CURLOPT_RETURNTRANSFER,$this->options['setOptions'][CURLOPT_RETURNTRANSFER]) : $this->setOption(CURLOPT_RETURNTRANSFER,TRUE);
	    isset($this->options['setOptions'][CURLOPT_FOLLOWLOCATION]) ? $this->setOption(CURLOPT_FOLLOWLOCATIO,$this->options['setOptions'][CURLOPT_FOLLOWLOCATION]) : $this->setOption(CURLOPT_FOLLOWLOCATION,TRUE);
            isset($this->options['setOptions'][CURLOPT_FAILONERROR]) ? $this->setOption(CURLOPT_FAILONERROR,$this->options['setOptions'][CURLOPT_FAILONERROR]) : $this->setOption(CURLOPT_FAILONERROR,FALSE);
        }

	/*
	@MAIN FUNCTION FOR PROCESSING CURL
	*/
	public function run($uri,$method=self::METHOD_GET,$data=null,$headers)
        {
                $this->setUri($uri);

                if( !$this->uri )
                    throw new EActiveResourceRequestException(Yii::t('EActiveResourceRequest', 'No uri set') );

                $this->ch = curl_init();
                
                $this->setOption(CURLOPT_URL,$this->uri);
                $this->setOption(CURLOPT_CUSTOMREQUEST,$method);
                $this->setOption(CURLOPT_HTTPHEADER,$headers);


                switch($method)
                {
                    //only PUT and POST need some preprocessing of the data
                    case self::METHOD_PUT:
                        //If you want to PUT a string and not an array (like you would with POST)
                        //you have to use a "fake" file with the string as content
                        //If using an array you can use PUT as you would with POST
                        if(isset($data))
                        {
                            /*
                            //throw new CException($data);
                            // Clean up string
                            $putString = stripslashes($data);
                            // Put string into a temporary file
                            $putData = tmpfile();
                            // Write the string to the temporary file
                            fwrite($putData, $putString);
                            // Move back to the beginning of the file
                            fseek($putData, 0);

                            $this->setOption(CURLOPT_BINARYTRANSFER, true);
                            $this->setOption(CURLOPT_RETURNTRANSFER, true);
                            $this->setOption(CURLOPT_PUT, true);
                            $this->setOption(CURLOPT_INFILE, $putData);
                            $this->setOption(CURLOPT_INFILESIZE, strlen($putString));
                            break;
                             *
                             */
                            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);
                        }
                    case self::METHOD_POST:
                        $this->setOption(CURLOPT_POSTFIELDS, $data);
                        break;
                }


                $this->setDefaults();

                //set options that were defined via config
                if(isset($this->options['setOptions']))
                    foreach($this->options['setOptions'] as $k=>$v)
                        $this->setOption($k,$v);

                isset($this->options['login']) ?  $this->setHttpLogin($this->options['login']['username'],$this->options['login']['password']) :  null;
                isset($this->options['proxy']) ? $this->setProxy($this->options['proxy']['url'],$this->options['proxy']['port']) : null;

                if(isset($this->options['proxylogin']))
                {
                    if(!isset($this->options['proxy']))
                        throw new EActiveResourceRequestException( Yii::t('EActiveResourceRequest', 'You have to define "proxy" with arrays in order to use proxylogins.') );
                    else
                        $this->setProxyLogin($this->options['login']['username'],$this->options['login']['password']);
		}

                $response = curl_exec($this->ch);
                $responseInfo=curl_getinfo($this->ch);
                $responseUri=$responseInfo['url'];
                $responseCode=$responseInfo['http_code'];

                if($responseCode && $responseCode<400)
                    return $response;
                else
                {
                    $errorMessage="The requested uri returned an error with status code $responseCode";

                    switch ($responseCode)
                    {
                        case 0:
                            Yii::trace($response,'ext.EActiveResource');
                            throw new EActiveResourceRequestException('No response. Service may be down');

                        case 400:
                            Yii::trace($response,'ext.EActiveResource');
                            throw new EActiveResourceRequestBadRequestException($errorMessage, $responseCode);
                        case 401:
                            Yii::trace($response,'ext.EActiveResource');
                            throw new EActiveResourceRequestUnauthorizedAccessException($errorMessage, $responseCode);

                            
                        case 403:
                            Yii::trace($response,'ext.EActiveResource');
                            throw new EActiveResourceRequestForbiddenException($errorMessage, $responseCode);
                        case 404:
                            Yii::trace($response,'ext.EActiveResource');
                            throw new EActiveResourceRequestNotFoundException($errorMessage, $responseCode);
                        case 405:
                            Yii::trace($response,'ext.EActiveResource');
                            throw new EActiveResourceRequestMethodNotAllowedException($errorMessage, $responseCode);
                        case 406:
                            Yii::trace($response,'ext.EActiveResource');
                            throw new EActiveResourceRequestNotAcceptableException($errorMessage, $responseCode);


                        case 407:
                            Yii::trace($response,'ext.EActiveResource');
                            throw new EActiveResourceRequestProxyAuthenticationException($errorMessage, $responseCode);
                        case 408:
                            Yii::trace($response,'ext.EActiveResource');
                            throw new EActiveResourceRequestTimeoutException($errorMessage, $responseCode);
                        

                        default:
                            Yii::trace($response,'ext.EActiveResource');
                            throw new EActiveResourceRequestException($errorMessage, $responseCode);
                    }
                }
      }

}
?>
