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
        
        private $_header;
        private $_headerString="";
        private $_contentType;
        private $_acceptType;

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
                throw new EActiveResourceRequestException(Yii::t('EActiveResource', 'Options must be an array'));
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
                        throw new EActiveResourceRequestException(Yii::t('EActiveResource', '{k} is not a valid option', array('{k}'=>$key)));
                    }
                    $type = gettype($val);
                    if ((!is_array($validOptions[$key]['type']) && ($type != $validOptions[$key]['type'])) || (is_array($validOptions[$key]['type']) && !in_array($type, $validOptions[$key]['type'])))
                    {
                        throw new EActiveResourceRequestException(Yii::t('EActiveResource', '{k} must be of type {t}',
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
            !isset($this->options['timeout'])  ?  $this->setOption(CURLOPT_TIMEOUT,120) : $this->setOption(CURLOPT_TIMEOUT,$this->options['timeout']);
            isset($this->options['setOptions'][CURLOPT_HEADER]) ? $this->setOption(CURLOPT_HEADER,$this->options['setOptions'][CURLOPT_HEADER]) : $this->setOption(CURLOPT_HEADER,FALSE);
            isset($this->options['setOptions'][CURLOPT_RETURNTRANSFER]) ? $this->setOption(CURLOPT_RETURNTRANSFER,$this->options['setOptions'][CURLOPT_RETURNTRANSFER]) : $this->setOption(CURLOPT_RETURNTRANSFER,TRUE);
	    isset($this->options['setOptions'][CURLOPT_FOLLOWLOCATION]) ? $this->setOption(CURLOPT_FOLLOWLOCATION,$this->options['setOptions'][CURLOPT_FOLLOWLOCATION]) : $this->setOption(CURLOPT_FOLLOWLOCATION,TRUE);
            isset($this->options['setOptions'][CURLOPT_FAILONERROR]) ? $this->setOption(CURLOPT_FAILONERROR,$this->options['setOptions'][CURLOPT_FAILONERROR]) : $this->setOption(CURLOPT_FAILONERROR,FALSE);
        }
        
        /**
         * The callback function for curlopt_HEADERFUNCTION adding each headerline to $this->_headerString
         * @param curl handle $ch the curl handle needed for the callback
         * @param string $header a single header line
         * @return int strlen of the line
         */
        protected function addHeaderLine($ch,$header)
        {
            $this->_headerString.=$header;
            return strlen($header);
        }
        
        public function setHeader($header)
        {
            $this->_header=$header;
        }
        
        public function setCustomHeader($customHeader)
        {
            if($customHeader=='')
                return;
            $this->setHeader($this->getHeader().$customHeader);
        }
        
        public function getHeader()
        {
            if(isset($this->_header))
                    return $this->_header;
        }
        
        public function setContentType($contentType)
        {
            $this->_contentType=$contentType;
        }
        
        public function getContentType()
        {
            if(isset($this->_contentType))
                    return $this->_contentType;
        }
        
        public function setAcceptType($acceptType)
        {
            $this->_acceptType=$acceptType;
        }
        
        public function getAcceptType()
        {
            if(isset($this->_acceptType))
                    return $this->_acceptType;
        }

	/*
	@MAIN FUNCTION FOR PROCESSING CURL
	*/
	public function run($uri,$method=self::METHOD_GET,$data=null)
        {
                $this->setUri($uri);

                if( !$this->uri )
                    throw new EActiveResourceRequestException(Yii::t('EActiveResourceRequest', 'No uri set') );

                $this->ch = curl_init();
                
                $parsedData=null;
                
                if(!is_null($data))
                {
                    switch($this->_contentType)
                    {
                    case EActiveResourceRequest::APPLICATION_JSON:
                        $parsedData=EActiveResourceParser::arrayToJSON($data);
                        break;
                    case EActiveResourceRequest::APPLICATION_XML:
                        $parsedData=EActiveResourceParser::arrayToXML($data);
                        break;
                    default:
                        throw new CException('Content Type '.$this->_contentType.' not implemented!');
                    }
                }
                
                //set standard headers
                if(!is_null($parsedData))
                {
                    $headers=array(
                        'Content-Length: '  .strlen($parsedData),
                        'Content-Type: '    .$this->getContentType(),
                        'Accept: '          .$this->getAcceptType(),
                    );
                }
                else {
                    $headers=array(
                        'Accept: '          .$this->getAcceptType(),
                    );
                }
                
                $this->setHeader($headers);
                
                $this->setOption(CURLOPT_URL,$this->uri);
                $this->setOption(CURLOPT_CUSTOMREQUEST,$method);
                $this->setOption(CURLOPT_HTTPHEADER,$this->getHeader());
                $this->setOption(CURLOPT_HEADERFUNCTION,array($this,'addHeaderLine'));
                                
                if(($method==self::METHOD_PUT || $method==self::METHOD_POST) && !is_null($parsedData))
                    $this->setOption(CURLOPT_POSTFIELDS, $parsedData);
                
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
                
                if(!is_null($parsedData))
                    Yii::trace('Sending '.$method.' request to '.$uri.' with content-type:'.$this->getContentType().', accept: '.$this->getAcceptType().' and data: '.$parsedData,'ext.EActiveResource.request');
                else
                    Yii::trace('Sending '.$method.' request to '.$uri.' without data, accepting: '.$this->getAcceptType(),'ext.EActiveResource.request');
                
                
                $response=new EActiveResourceResponse(curl_exec($this->ch),curl_getinfo($this->ch),$this->_headerString);
                
                curl_close($this->ch);
                return $response;
      }

}
?>
