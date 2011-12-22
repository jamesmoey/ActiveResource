<?php
/**
 * @author Johannes "Haensel" Bauer (thehaensel@gmail.com)
 * @since version 0.4
 */

/**
 * The EActiveResourceConnection component is used to define the configuration for all resources used in a project and allows caching
 * responses the same CActiveRecord does.
 * Example: 'activeresource'=>array(resources'=>array('MyClassName'=>array(
            'site'=>'http://api.aRESTservice.com',
            'resource'=>'people',
            'contenttype'=>'application/json',
            'accepttype'=>'application/json',
            'fileextension'=>'.json',
        ))
 */

class EActiveResourceConnection extends CApplicationComponent
{
    public $queryCachingDuration=0;
    public $queryCachingDependency;
    public $queryCachingCount=0;
    public $queryCacheID='cache';
    
    public $resources=array();
    
    /**
     * Gets the configuration array for a specific EActiveResource object
     * @param string $activeResourceClassName The name of the class for which the configuration should be returned e.g(get_class(TwitterUser))
     * @return array The configuration as defined in the config under 'resources'=>'classname' for the specified active resource class
     */
    public function getResourceConfiguration($activeResourceClassName)
    {
        if(isset($this->resources[$activeResourceClassName]))
                return $this->resources[$activeResourceClassName];
        else
            throw new EActiveResourceException('No configuration for class '.$activeResourceClassName.' found!');
    }
    
    /**
     * Used for caching responses
     * @param integer $duration The caching duration in seconds
     * @param CCacheDependency $dependency The dependency used for caching (Note: CDbCacheDependency won't work with for obvious reasons)
     * @param integer $queryCount The number of queries that will be cached. Defaults to 1 meaning only the first response will be cached
     * @return EActiveResourceConnection
     */
    public function cache($duration, $dependency=null, $queryCount=1)
    {
        $this->queryCachingDuration=$duration;
        $this->queryCachingDependency=$dependency;
        $this->queryCachingCount=$queryCount;
        return $this;
    }
    
    /**
     * Creates a new request, sends and receives the data, uses caching if defined by the user.
     * @param string $uri The uri this request is sent to.
     * @param string $method The method (GET,PUT,POST,DELETE)
     * @param array $customHeader A customHeader to be sent
     * @param array $data The data to be sent as array
     * @return EActiveResourceResponse The response object
     */
    public function sendRequest(EActiveResourceRequest $request)
    {           
        ///LOOK FOR CACHED RESPONSES FIRST
        if($this->queryCachingCount>0
                        && $this->queryCachingDuration>0
                        && $this->queryCacheID!==false
                        && ($cache=Yii::app()->getComponent($this->queryCacheID))!==null)
        {
            $this->queryCachingCount--;
            $cacheKey='yii:eactiveresourcerequest:'.$request->getUri().':'.$request->getMethod().':'.$this->recursiveImplode('-',$request->getHeader());
            $cacheKey.=':'.$this->recursiveImplode('#',$request->getData());
            if(($result=$cache->get($cacheKey))!==false)
            {
                    Yii::trace('Response found in cache','ext.EActiveResource.EActiveResourceConnection');
                    return $result;
            }
        }

        $response=$request->run();

        //CACHE RESULT IF CACHE IS SET
        if(isset($cache,$cacheKey))
            $cache->set($cacheKey, $response, $this->queryCachingDuration, $this->queryCachingDependency);

        return $response;
    }
    
    /**
     * Implodes a multidimensional array to a simple string (used to create unique cache keys)
     * @return string The array formatted as a string
     */
    protected function recursiveImplode($glue, $pieces)
    {
        $return = "";
        
        if($pieces===null)
            return;

        if(!is_array($glue))
            $glue = array($glue);
        
        $currentGlue = array_shift($glue);

        if(!count($glue))
            $glue = array($currentGlue);
        
        if(!is_array($pieces))
            return (string) $pieces;
        
        foreach($pieces as $sub)
            $return .= $this->recursiveImplode($glue, $sub) . $currentGlue;

        if(count($pieces))
            $return=substr($return,0,strlen($return)-strlen($currentGlue));

        return $return;
    }
        
}
?>
