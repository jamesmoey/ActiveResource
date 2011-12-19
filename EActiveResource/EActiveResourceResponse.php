<?php
/**
 * @author Johannes "Haensel" Bauer
 * @since 0.3
 */

/**
 * This class encapsulates a curl response and is used to extract response headers,
 * raw data, parsed data and throws response exceptions according to the http response codes returned by the service
 */
class EActiveResourceResponse
{
    private $_rawData;
    private $_parsedData;
    private $_info;
    private $_acceptType;
    private $_headerString;//the raw header
    private $_header;
    
    /**
     * Constructor
     * @param string $rawData The raw data returned by the service (xml,json etc.)
     * @param array $info The curl response info array
     * @param string $headerString The header string returned by the service
     */
    public function __construct($rawData,$info,$headerString,$acceptType)
    {
        $this->_rawData=$rawData;
        $this->_headerString=$headerString;
        $this->_info=$info;
        $this->_acceptType=$acceptType;
        $this->parseHeaders();
        $this->hasErrors();
        $this->parseData();
    }
    
    /**
     * Getter for returning the raw response of the service
     * @return string The raw data
     */
    public function getRawData()
    {
            return $this->_rawData;
    }
    
    /**
     * Getter for returing the parsed data as array
     * @return array the parsed data
     */
    public function getData()
    {
            return $this->_parsedData;
    }
    
    /**
     * Getter for returning the curl info of this response
     * @return array the curl info
     */
    public function getInfo()
    {
            return $this->_info;
    }
    
    /**
     * Getter for the header
     * @return array the header
     */
    public function getHeader()
    {
            return $this->_header;
    }
    
    /**
     * Internally used to parse the raw response from the service
     * and create an PHP array according to the accept type (JSON, XML)
     */
    public function parseData()
    {
        Yii::trace("Response took ".$this->_info['total_time']." seconds:\n".$this->getRawData(),'ext.EActiveResource.response');
        
        switch($this->_acceptType)
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
                    throw new EActiveResourceException('Accept Type '.$info['content_type'].' not implemented!');
            }
    }
    
    /**
     * Internally used to create an array out of the header string returned by the service. Use getHeader() to get the result
     */
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
    
    /**
     * Internally used to check the response codes. Throws errors if errors occured
     * @return boolean returns false if no errors occurred, throws exception if errors occured
     */
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
