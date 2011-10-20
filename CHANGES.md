#Changes:

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