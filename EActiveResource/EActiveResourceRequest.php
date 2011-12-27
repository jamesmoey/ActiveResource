<?php
/**
 * @author Johannes "Haensel" Bauer, heavily influenced by Igor Ivanović who created the cUrl extension for Yii
 * @since 0.1
 */

/**
 * This class is used to send and receive cURL requests to the service used by an ActiveResource.
 * It is heavily influenced by Igor Ivanović's cURL extension for Yii although a bit modified to allow sending PUT and DELETE
 * requests and different content/accept types etc.
 * @link: http://www.yiiframework.com/extension/curl
 */
class EActiveResourceRequest
{
        protected $ch;
        
        private $_uri;
        private $_method;
        private $_data;
        private $_header;
        private $_customHeader;
        private $_contentType;
        private $_acceptType;
        private $_timeout=30;
                
        private $_headerString="";

        const APPLICATION_JSON  ='application/json';
        const APPLICATION_XML   ='application/xml';
        const APPLICATION_FORM_URL_ENCODED  ='application/x-www-form-urlencoded';

        const METHOD_GET    = 'GET';
        const METHOD_POST   = 'POST';
        const METHOD_PUT    = 'PUT';
        const METHOD_DELETE = 'DELETE';


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
	@DEFAULTS
	*/
        protected function setDefaults()
        {
            $this->setOption(CURLOPT_TIMEOUT,$this->getTimeOut());
            $this->setOption(CURLOPT_HEADER,FALSE);
            $this->setOption(CURLOPT_RETURNTRANSFER,TRUE);
	    $this->setOption(CURLOPT_FOLLOWLOCATION,TRUE);
            $this->setOption(CURLOPT_FAILONERROR,FALSE);
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
        
        /**
         * Set the timeout of this request in seconds
         * @param int $timeout  The timeout in seconds
         */
        public function setTimeOut($timeout)
        {
            $this->_timeout($timeout);
        }
        
        /**
         * Getter for the currently set timeout for this request
         * @return int timeout in seconds 
         */
        public function getTimeOut()
        {
            return $this->_timeout;
        }
        
        /**
         * Getter for the currently set header used by curl
         * @return array The header to be sent to the service
         */
        public function getHeader()
        {
            if(isset($this->_header))
                    return $this->_header;
            
            $customHeader=$this->getCustomHeader();
            if(!empty($customHeader))
                return $customHeader;
            else
                return $this->getStandardHeader();
            
        }
        
        /**
         * Getter for the "standard header". This method simply checks if there is data to be sent by this request and if so
         * sets the content type, content length and the accept type to create a basic header
         * @return array the standard header 
         */
        public function getStandardHeader()
        {
            
            //set standard headers
            if(!is_null($this->getParsedData()))
            {
                $header=array(
                    'Content-Length: '  .strlen($this->getParsedData()),
                    'Content-Type: '    .$this->getContentType(),
                    'Accept: '          .$this->getAcceptType(),
                );
            }
            else {
                $header=array(
                    'Accept: '          .$this->getAcceptType(),
                );
            }
            
            return $header;
        }

        /**
         * Sets the uri for this request
         * @param string $uri The uri
         */
        public function setUri($uri)
        {
            if(!preg_match('!^\w+://! i', $uri))
            {
                $url = 'http://'.$uri;
            }
            $this->_uri = $uri;
        }
        
        /**
         * Get the current uri for this request
         * @return string the uri 
         */
        public function getUri()
        {
            if(isset($this->_uri))
                    return $this->_uri;
            else
                return null;
        }
        
        /**
         * Getter for the data set for this request
         * @return array the data set for this request (a PHP array)
         */
        public function getData()
        {
            if(isset($this->_data))
                    return $this->_data;
            else
                return null;
        }
        
        /**
         * Sets the data for this request
         * @param array $data The data to be sent as PHP array
         */
        public function setData($data)
        {
            $this->_data=$data;
        }
        
        /**
         * Parsed the data according to the content type to build a valid request (JSON or XML)
         * @return string the JSON or XML encoded string, null if no data is set 
         */
        public function getParsedData()
        {
            if(isset($this->_parsedData))
                    return $this->_parsedData;
            
            if(!is_null($this->getData()))
            {
                switch($this->getContentType())
                {
                    case self::APPLICATION_JSON:
                        $parsedData=EActiveResourceParser::arrayToJSON($this->getData());
                        break;
                    case self::APPLICATION_XML:
                        $parsedData=EActiveResourceParser::arrayToXML($this->getData());
                        break;
                    default:
                        throw new CException('Content Type '.$this->getContentType().' not implemented!');
                }
                
                return $this->_parsedData=$parsedData;
            }
            
            return null;
        }
        
        /**
         * Get the currently used method for this request (GET,PUT,POST,DELETE)
         * @return string The method used
         */
        public function getMethod()
        {
            if(isset($this->_method))
                    return $this->_method;
            else
                return self::METHOD_GET;
        }
        
        /**
         * Set the method to be used by this request (GET,PUT,POST,DELETE)
         * @param string $method The method. Defaults to GET 
         */
        public function setMethod($method=self::METHOD_GET)
        {
            $this->_method=$method;
        }
        
        /**
         * Get the custom header set for this request
         * @return array the custom header array 
         */
        public function getCustomHeader()
        {
            if(isset($this->_customHeader))
                    return $this->_customHeader;
            else
                return array();
        }
        
        /**
         * Sets a custom header for this request. It will be mearched with the standard header array
         * defining accept type, content length and content type.
         * @param array $customHeader the custom header array
         */
        public function setCustomHeader($customHeader)
        {
            $this->_customHeader=$customHeader;
        }
                
        /**
         * Sets the content type
         * @param string $contentType 
         */
        public function setContentType($contentType)
        {
            $this->_contentType=$contentType;
        }
        
        /**
         * Get the currently used content type
         * @return string the content type
         */
        public function getContentType()
        {
            if(isset($this->_contentType))
                    return $this->_contentType;
        }
        
        /**
         * Sets the accept type
         * @param string $acceptType 
         */
        public function setAcceptType($acceptType)
        {
            $this->_acceptType=$acceptType;
        }
        
        /**
         * Get the currently used accept type
         * @return string the accept type
         */
        public function getAcceptType()
        {
            if(isset($this->_acceptType))
                    return $this->_acceptType;
        }

	/**
         * Sends the request and returns the response object.
         * @return EActiveResourceResponse The response object 
         */
	public function run()
        {            
                if(is_null($this->getUri()))
                    throw new EActiveResourceRequestException(Yii::t('EActiveResourceRequest', 'No uri set') );
                
                $this->ch = curl_init();
                                                
                $this->setOption(CURLOPT_URL,$this->getUri());
                $this->setOption(CURLOPT_CUSTOMREQUEST,$this->getMethod());
                $this->setOption(CURLOPT_HTTPHEADER,$this->getHeader());
                $this->setOption(CURLOPT_HEADERFUNCTION,array($this,'addHeaderLine'));
                                
                if(!is_null($this->getParsedData()))
                    $this->setOption(CURLOPT_POSTFIELDS, $this->getParsedData());
                                
                $this->setDefaults();
                
                if(!is_null($this->getParsedData()))
                    Yii::trace('Sending '.$this->getMethod().' request to '.$this->getUri().' with content-type:'.$this->getContentType().', accept: '.$this->getAcceptType().' and data: '.$this->getParsedData(),'ext.EActiveResource.request');
                else
                    Yii::trace('Sending '.$this->getMethod().' request to '.$this->getUri().' without data, accepting: '.$this->getAcceptType(),'ext.EActiveResource.request');
                
                $response=new EActiveResourceResponse(curl_exec($this->ch),curl_getinfo($this->ch),$this->_headerString,$this->getAcceptType(),curl_error($this->ch));
                                
                curl_close($this->ch);
                
                return $response;
      }
      
}
?>
