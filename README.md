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
     /* The id that uniquely identifies a person. This attribute is not defined as a property      
      * because we don't want to send it back to the service like a name, surname or gender etc.
      */
     public $id;

     public static function model($className=__CLASS__)
     {
         return parent::model($className);
     }

     /* Let's define some properties and their datatypes
     public function properties()
     {
         return array(
             'name'=>array('type'=>'string'),
             'surname'=>array('type'=>'string'),
             'gender'=>array('type'=>'string'),
             'age'=>array('type'=>'integer'),
             'married'=>array('type'=>'boolean'),
             'salary'=>array('type'=>'double'),
         );
     }

     /* Define rules as usual */
     public function rules()
     {
         return array(
             array('name,surname,gender,age,married,salary','safe'),
             array('age','numerical','integerOnly'=>true),
             array('married','boolean'),
             array('salary','numerical')
         );
     }

     /* Add some custom labels for forms etc. */
     public function attributeLabels()
     {
         return array(
             'name'=>'First name',
             'surname'=>'Last name',
             'salary'=>'Your monthly salary',
         );
     }
 }
~~~

##Usage:

~~~

    /* sends GET to http://api.example.com/person/1 and populates a single Person model*/
    $person=Person::model()->findById(1);

    /* sends GET to http://api.example.com/person and populates Person models with the response */
    $persons=Person::model()->findAll();

    /* create a resource
    $person=new Person;
    $person->name='A name';
    $person->age=21;
    $person->save(); //New resource, send POST request. Returns false if the model doesn't validate

    /* Updating a resource (sending a PUT request)
    $person=Person::model()->findById(1);
    $person->name='Another name';
    $person->save(); //Not at new resource, update it. Returns false if the model doesn't validate

    //or short version
    Person::model()->updateById(1,array('name'=>'Another name'));

    /* DELETE a resource
    $person=Person::model()->findById(1);
    $person->destroy(); //DELETE to http://api.example.com/person/1

    //or short version
    Person::model()->deleteById(1);