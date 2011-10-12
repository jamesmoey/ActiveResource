<?php

class EActiveResourceResponse
{
    private $_rawData;
    private $_parsedData;
    private $_info;
    private $_headerString;//the raw header
    private $_header;
    
    public function __construct($rawData,$info,$headerString)
    {
        $this->_rawData=$rawData;
        $this->_headerString=$headerString;
        $this->_info=$info;
        $this->parseHeaders();
        $this->hasErrors();
        $this->parseData();
    }
    
    public function getRawData()
    {
        if(isset($this->_rawData))
            return $this->_rawData;
    }
    
    public function getData()
    {
        if(isset($this->_parsedData))
            return $this->_parsedData;
    }
    
    public function getInfo()
    {
        if(isset($this->_info))
            return $this->_info;
    }
    
    public function getHeader()
    {
        if(isset($this->_header))
            return $this->_header;
    }
    
    public function parseData()
    {
        $info=$this->getInfo();
        
        if(isset($info['content_type']))
        {
            
            Yii::trace('The service responded with '.$this->getRawData(),'ext.EActiveResource.response');
            switch($info['content_type'])
                {
                    case EActiveResourceRequest::APPLICATION_JSON:
                        $this->_parsedData=EActiveResourceParser::JSONtoArray($this->getRawData());
                        break;
                    case EActiveResourceRequest::APPLICATION_XML:
                        $this->_parsedData=EActiveResourceParser::XMLtoArray($this->getRawData());
                        break;
                    case null:
                        break;
                    default:
                        throw new EActiveResourceException('The service responded with content Type '.$info['content_type'].' which is not implemented!');
                }
        }
    }
    
    protected function parseHeaders()
    {
        $retVal = array();
        $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $this->_headerString));
        foreach( $fields as $field ) {
            if( preg_match('/([^:]+): (.+)/m', $field, $match) ) {
                $match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower(trim($match[1])));
                if( isset($retVal[$match[1]]) ) {
                    $retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
                } else {
                    $retVal[$match[1]] = trim($match[2]);
                }
            }
        }
        $this->_header=$retVal;
    }
      
    protected function hasErrors()
    {
        $responseInfo=$this->getInfo();
        $responseUri=$responseInfo['url'];
        $responseCode=$responseInfo['http_code'];

        if($responseCode && $responseCode<400)
            return false;
        else
        {
            if(YII_DEBUG)
                $errorMessage="The requested uri returned an error with status code $responseCode \n\n".$this->getRawData();
            else
                $errorMessage="The requested uri returned an error with status code $responseCode";

            switch ($responseCode)
            {
                case 0:
                    Yii::trace('ERROR RESPONSE: '.$this->getRawData(),'ext.EActiveResource');
                    throw new EActiveResourceRequestException('No response. Service may be down');

                case 400:
                    Yii::trace('ERROR RESPONSE: '.$this->getRawData(),'ext.EActiveResource');
                    throw new EActiveResourceRequestException_BadRequest($errorMessage, $responseCode);
                case 401:
                    Yii::trace('ERROR RESPONSE: '.$this->getRawData(),'ext.EActiveResource');
                    throw new EActiveResourceRequestException_UnauthorizedAccess($errorMessage, $responseCode);


                case 403:
                    Yii::trace('ERROR RESPONSE: '.$this->getRawData(),'ext.EActiveResource');
                    throw new EActiveResourceRequestException_Forbidden($errorMessage, $responseCode);
                case 404:
                    Yii::trace('ERROR RESPONSE: '.$this->getRawData(),'ext.EActiveResource');
                    throw new EActiveResourceRequestException_NotFound($errorMessage, $responseCode);
                case 405:
                    Yii::trace('ERROR RESPONSE: '.$this->getRawData(),'ext.EActiveResource');
                    throw new EActiveResourceRequestException_MethodNotAllowed($errorMessage, $responseCode);
                case 406:
                    Yii::trace('ERROR RESPONSE: '.$this->getRawData(),'ext.EActiveResource');
                    throw new EActiveResourceRequestException_NotAcceptable($errorMessage, $responseCode);


                case 407:
                    Yii::trace('ERROR RESPONSE: '.$this->getRawData(),'ext.EActiveResource');
                    throw new EActiveResourceRequestException_ProxyAuthentication($errorMessage, $responseCode);
                case 408:
                    Yii::trace('ERROR RESPONSE: '.$this->getRawData(),'ext.EActiveResource');
                    throw new EActiveResourceRequestException_Timeout($errorMessage, $responseCode);


                default:
                    Yii::trace('ERROR RESPONSE: '.$this->getRawData(),'ext.EActiveResource');
                    throw new EActiveResourceRequestException($errorMessage, $responseCode);
            }
        }
    }
}
?>
