#EActiveResource for Yii

...is an extension for the Yii PHP framework allowing the user to create models that use RESTful services as persistent storage.
The implementation is inspired by Yii's CActiveRecord class (http://www.yiiframework.com/doc/api/1.1/CActiveRecord/) and the Ruby on Rails implementation of ActiveResource (http://api.rubyonrails.org/classes/ActiveResource/Base.html).

##ATTENTION: 
1. This class is completely schemaless by default meaning that you can assign any attribute on the fly. Because of that you can't use any magic SETTERS like
$model->attributes=array('name'=>'Haensel') as this would add an attribute called "attributes" to the model with array('name'=>'Haensel') as value. 
Always use the corresponding methods (like setAttributes()).

2. The only validation rule that is ignored when setting a dynamic attribute is the 'safe' rule. So setting $model->someAttribute='TEST' will set the
attribute even if 'someAttribute' wasn't explicitly set to being 'safe'. This means that $model->save() will POST the model to the service because the model validates.
If you use other rules like 'required' then validation is performed as usual and validation will fail if these restrictions are not met.

##HINT:
As there are thousands of different REST services out there that use a thousand different approaches it can be tricky to debug errors. Because of that I added extensive
tracing to all major functions, so you should always be able to see every request, which method it used and how the service responded. Just enable the tracing functionality of Yii
and look for the category "ext.EActiveResource"

##INSTALL:

1.) Add the extension to Yii by placing it in your application's extension folder (for example '/protected/extensions')
2.) Edit your applications main.php config file and add 'application.extensions.EActiveResource.*' to your import definitions
3.) Add the configuration for your resources to the main config

	    'activeresource'=>array(
			'resources'=>array(
				'Person'=>array(
            		'site'=>'http://api.aRESTservice.com',
            		'resource'=>'people',
            		'contenttype'=>'application/json',
            		'accepttype'=>'application/json',
            		'fileextension'=>'.json',
       		)),
       		'cacheId'=>'SomeCacheComponent')
       		
4.) Now create a class extending EActiveResource like this (don't forget the model() function!):

##QUICK OVERVIEW:

~~~

class Person extends EActiveResource
{

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }
}
?>
~~~
~~~

$person=Person::model()->findById(1); //sends GET 'http://api.aRESTservice.com/people/1.json'
$person->name='Haensel'; //dynamically sets an attribute. No 'safe' rule is needed. If you want to set attributes according to your rules() array (like you would with CActiveResource) use $person->setAttributes(array('name'=>'Haensel'))
$person->save(); //Updates the resource because it is obvisiouly not a new resource (found by a finder method) => PUT request to http://api.aRESTservice.com/people/1 with data '{'name':'Haensel'}'

//creating a new Person

$person = new Person; //no request
$person->name='Haensel'; //no request, sets the attribute "name" to "Haensel" even if no 'safe' rule is set.
$person->gender='m'; //no request, same as with name
$person->save(); // POST request to http://api.aRESTservice.com/people with data '{'name';'Haensel','gender':'m'}'
~~
//using validation rules

add the rules() function to your model

~~~

class Person extends EActiveResource
{

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }
    
    public function rules()
    {
    	return array(
    		array('name','required');
    		array('gender','numerical');
    	);
    }
}
?>
~~~
~~~
$person=new Person;
$person->setAttributes(array(
	'name'=>'Haensel',
	'gender'=>'m'
));
$person->save(); //validation fails, no POST request is sent to the service. You can get the error messages like you would with CActiveRecord (which uses the CModel methods getErrors()).

$person=new Person;
$person->setAttributes(array(
	'name'=>'Haensel',
	'gender'=>1
));
$person->save(); // VALIDATED. Sending POST request to http://api.aRESTservice.com/people with data '{'name':'Haensel','gender':1}'
~~~