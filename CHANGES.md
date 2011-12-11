#Changes:

##Version 0.4 (not backwards compatible!):

###1. Introducing EActiveResourceConnection 
Instead of defining the rest() array within the EActiveResource model all configurations are now made within the Yii config using the "activeresource" application component.
Example: 

        'activeresource'=>array(
			'resources'=>array(
				'MyClassName'=>array(
            		'site'=>'http://api.aRESTservice.com',
            		'resource'=>'people',
            		'contenttype'=>'application/json',
            		'accepttype'=>'application/json',
            		'fileextension'=>'.json',
       		)),
       		'cacheId'=>'SomeCacheComponent')
       		
The rest() method still exists, but retrieves the configuration array from the 'activeresource' component now. Feel free to overwrite this method if you want to create some customized rest configurations
       		
###2. Caching
Version 0.4 now enables caching of responses. Example:
~~~
[php]
		MyModel::model()->cache(10)->findById(1);
~~~
will cache the response of the service for 10 seconds. The syntax is the same as with CActiveRecord, so you can set dependencies (no CDbCacheDependency for obvious reasons) and query count.

###3. Custom headers
Custom headers now fully replace standard headers so 'contenttype' and 'accepttype' as defined in the config will be ignored and will have to be set manually in such a case

###4. sendRequest method moved from EActiveResource to EActiveResourceConnection
The idea is to provide better seperation between the model and the connection layer

##Version 0.3 (not backwards compatible!):

###1. Fully object oriented approach for requests/responses 
A request now always creates an EActiveResourceRequest object (which now has several setter methods for url, data, content/accept types,headers etc.) and returns an EActiveResourceResponse object which essentually is an object encapsulating the curl response. So instead of writing

~~~
[php]

$response=$this->getRequest();

//you have to use
$response=$this->getRequest()->getData(); //to get the data array like in previous versions
$response=$this->getRequest()->getHeader(); //to get the header
$response=$this->getRequest()->getInfo(); //to get curl specific info

~~~

###2. Custom headers
There are cases when one has to get/set headers (authorization,pagination of results etc.). You can now do this by passing a header array to the request functions like

~~~
[php]

$response=$this->getRequest(1,null,$customHeader=array('content-type'=>'application/json'));
//or
$response=$this->customGetRequest('http://api.example.com/people/1/',null,$customHeader=array('content-type'=>'application/json'));

//now you can extraxt the header response with
$responseHeader=$response->getHeader(); //will give you an array of header information

~~~

###3. Change in naming convention for exceptions
For better readibility all error responses now throw EActiveResourceRequestException_EXPETIONTYPE (e.g.:EActiveResourceRequestException_NotFound) exceptions. Take that into account when catching them

###4. Manual creation of request
Because of the object oriented approach you are now able to create a request without using the convenient $this->xxxRequest() methods

~~~
[php]

$request=new EActiveResourceRequest;
$request->setUri($uri);
$request->setMethod('GET');
$request->setContentType('application/json');
$request->setAcceptType('application/json');
$request->setTimeout(30);

$response=$request->run();

~~~