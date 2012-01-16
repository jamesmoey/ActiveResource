#Changes:

##Version 0.7:

###1. Seperation of connection information and resource information:
The EActiveResourceConnection component now only holds information that is used
by all resources using it as a connection like site,auth,ssl,contentType,acceptType. Resource
specific information has to be defined in the rest() method of each resource.

			'activeresource'=>array(
            	'class'=>'EActiveResourceConnection',
        		'site'=>'http://api.aRESTservice.com',
        		'contentType'=>'application/json',
            	'acceptType'=>'application/json',
            	'auth'=>array(
            	'type'=>'basic', //or digest
            		'username'=>'#####',
            		'password'=>'#####'
            	)
       		)),
       		'queryCacheId'=>'SomeCacheComponent')
       		
       		///in the resource itself
       		public function rest()
    		{
		 		return CMap::mergeArray(
		 			parent::rest(),
		 			array(
		 				'resource'=>'people',
		 			)
		 		);
     		}
     		
By default all resources use the "activeresource" component. If you want to use more than one
service you'll have to define additional components and override the connectionName() method.
Here's an example:

			'mySecondService'=>array(
            	'class'=>'EActiveResourceConnection',
        		'site'=>'http://api.aRESTservice.com',
        		'contentType'=>'application/json',
            	'acceptType'=>'application/json',
            	'auth'=>array(
            	'type'=>'basic', //or digest
            		'username'=>'#####',
            		'password'=>'#####'
            	)
       		)),
       		'queryCacheId'=>'SomeCacheComponent')
       		
       		///in the resource itself
       		public function connectionName()
    		{
		 		return 'mySecondService';
     		}
     		
###2. Seperation between queries and data manipulating requests
In previous versions one could cache PUT, DELETE, POST requests which could lead to strange
behaviors. By default, caching is only used by the finder methods which now use the query()
function in EActiveResource instead of the sendRequest method used by all other methods.

###3. BeforeFind events
All finder methods now fire a beforeFind event before they are executed.

###4. 

##Version 0.6:

###1. Added support for http auth
You can now use the authentication mechanisms provided by curl like basic or digest auth. To do that
you'll have to add this to your resource config

            'activeresource'=>array(
            'class'=>'EActiveResourceConnection',
			'resources'=>array(
				'MyClassName'=>array(
            		'site'=>'http://api.aRESTservice.com',
            		'resource'=>'people',
            		'contenttype'=>'application/json',
            		'accepttype'=>'application/json',
            		'auth'=>array(
            			'type'=>'basic', //or digest
            			'username'=>'#####',
            			'password'=>'#####'
            		)
       		)),
       		'queryCacheId'=>'SomeCacheComponent')
       		
###2. Url routes
Every method (like findById)  now uses a route defined via the routes() method which by default
looks like

			public function routes()
    		{
        		return array(
            		'resource'=>':site/:resource/:id',
            		'collection'=>':site/:resource',
        		);
    		}
    		
:site, :resource and :id are placeholders that will be replaced by the values defined in the resource
configuration. Thus 

			$this->postRequest('collection') //POST to http://api.aRESTservice.com/people
			Person::model()->findById(10)->getRequest('resource') 
			//GET to http://api.aRESTservice.com/people/10
			
If you don't have the route defined the route parameter will be treated as url

			$this->getRequest('http://www.google.com') //GET to http://www.google.com

Defining custom routes

			public function routes()
    		{
        		return array(
            		'resource'=>':site/:resource/show/:id.json',
            		'collection'=>':site/:resource',
            		'someCustumRoute'=>':site/:resource/fired'
        		);
    		}
    		
    		////
    		$this->getRequest('fired') //GET to http://api.aRESTservice.com/people/fired

###3. Url parameters
To make adding url parameters easier all xxxRequest methods allow passing in a params array

			$this->getRequest('resource',array('fired'=>'true'))
			//GET to http://api.aRESTservice.com/people/10?fired=true
			$this->postRequest('collection',array('return'=>'json'),$data)
			//POST to http://api.aRESTservice.com/people?return=json with data defined in $data
			
###4. SSL support
You can use https by defining the ssl array in your resource config

			'activeresource'=>array(
            'class'=>'EActiveResourceConnection',
			'resources'=>array(
				'MyClassName'=>array(
            		'site'=>'https://api.aRESTservice.com',
            		'resource'=>'people',
            		'contenttype'=>'application/json',
            		'accepttype'=>'application/json',
                	'ssl'=>array(
                   		'verifyPeer'=>true,
                        'verifyHost'=>2,
                        'pathToCert'=>'/someCert.crt',
                    )
       		)),
       		'cacheId'=>'SomeCacheComponent')
       		
To understand what these options do I recommend reading
http://unitstep.net/blog/2009/05/05/using-curl-in-php-to-access-https-ssltls-protected-sites/

###5. Error tracing
Tracing info now includes headers and curl information for even better debugging
			
##Version 0.5:
This probably is the biggest update so far

###1. Schema definition:
When defining a model you now have to define its properties and datatypes so that only the
properties you define will get loaded. To do this you'll have to override the properties method like
this:

            public function properties()
            {
                return array(
                   'name'=>array('type'=>'string'),
                   'age'=>array('type'=>'integer'),
                );
            }

This has some major benefits: First, all of Yii's magic methods work as in CActiveRecord
(use $model->attributes instead of $model->setAttributes() etc.). Second, all attributes will
automatically be typecasted before they are sent to the service, so you can populate a record with
POST values without having to think about that any more. Third, you don't have to worry
that a change in one of the resource APIs will kill your system. If a new property is introduced by
a service which isn't defined in your properties() the property will simply be ignored by 
ActiveResource.

###2. Attribute labels:
Attribute labels now work as expected

###3. Removed support for embedded resources
The embedded resources added a lot of complexity and are a relatively rare use case. If you need
a feature like that you'll have to implement that on your own which probably is the best solution.

###4. Changed private attributes to protected 
This should allow extending classes more easily now.


##Version 0.4 (not backwards compatible!):

###1. Introducing EActiveResourceConnection 
Instead of defining the rest() array within the EActiveResource model all configurations are now made within the Yii config using the "activeresource" application component.
Example: 

          'activeresource'=>array(
            'class'=>'EActiveResourceConnection',
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