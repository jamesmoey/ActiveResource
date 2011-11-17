<?php
/**
 * @author Johannes "Haensel" Bauer
 * @since version 0.1
 */

/**
 * Active resource is ment to be used similar to an ActiveRecord model in Yii. In difference to ActiveRecord
 * the persistent storage of the model isn't a database but a RESTful service. The code is influenced by the
 * Rails implementation of ActiveResource found at @link http://api.rubyonrails.org/classes/ActiveResource/Base.html
 */
abstract class EActiveResource extends CModel
{

    //const IS_PROPERTY='IS_PROPERTY';
    const IS_ONE='IS_ONE';
    const IS_MANY='IS_MANY';
    
    private static $_models=array();
    
    private $_md;                           // The metadata object for this resource (e.g.: field names, default values)

    private $_new;
    private $_attributes=array();
    private $_embedded=array();

    /**
     * Constructor.
     * @param string $scenario scenario name. See {@link CModel::scenario} for more details about this parameter.
     */
    public function __construct($scenario='insert')
    {
        if($scenario===null) // internally used by populateRecord() and model()
		return;

	$this->setScenario($scenario);
        $this->setIsNewResource(true);
        $this->_attributes=$this->getMetaData()->attributeDefaults;

	$this->init();

	$this->attachBehaviors($this->behaviors());
	$this->afterConstruct();
    }

    /**
     * Returns the meta-data for this ActiveResource
     * @return EActiveResourceMetaData the meta for this ActiveResource class.
     */
    public function getMetaData()
    {
            if($this->_md!==null)
                    return $this->_md;
            else
                    return $this->_md=self::model(get_class($this))->_md;
    }

    /**
     * This method is used in EActiveMetaData to recive the attributes of the object without the complex logic of the CModel getAttributes() function
     * @return array All attributes of this model.
     */
    public function getAttributesArray()
    {
        return $this->_attributes;
    }

    /**
     * Use this function to define the communication between this class and the REST service.
     * <p>
     * <b>site</b>: Defines the baseUri of the REST service. Example.: http://iamaRESTapi/apiversion
     * <p>
     * <b>resource</b>:  the actual uri of the resource. Example: If we want to use a modelclass to represent resources of people the resource could be 'people' which would lead to an uri like 'http://iamaRESTapi/apiversion/people'.
     * <p>
     * <b>idProperty</b>: The id property of this class. If the service returns responses including the resource id then you should specify this attribute here. Example: The service response contains a valuefield called "_id" with the value "1". Specify "_id" as the idProperty in your configuration. Now update requests would automatically look for a field called "_id" and send their requests to 'http://iamaRESTapi/apiversion/people/1'
     * <p>
     * <b>container</b>: Sometimes all responses include additional meta information about a request or the number of hits etc and the actual modelobject is contained within a container like 'result'. If this is the case you can specify this container here to allow ActiveResource to only load attributes specified within this container (e.g.: "results").
     * <p>
     * <b>contenttype</b>: Defines the content type that is send via HTTP header and is used to determine how the data has to be converted from php. If you use 'application/json' then data will automatically be converted to JSON.
     * <p>
     * <b>accepttype</b>: Defines the accept type send via HTTP header. It is also used to convert the response back to a php readable format like an array of attributes. Define application/json to automatically convert JSON responses to PHP arrays.
     * <p>
     * <b>fileExtension</b>: This is used to append something like '.json' to every GET request. This can be useful if the service doesn't respect headers but uses a formatextension to know what type of response you are looking for. Always remember to use a '.' in front of the extension!
     * <p>
     * <b>embedded</b>: Some services respond with an complex object containing other resources (like Twitter does by also returning user objects when requesting statuses). If you know that a certain field (like 'user') contains another object that you defined already defined as a subclass of EActiveResource than use the following syntax:
     * <ul>
     * <li>array('user'=>array(self::IS_ONE,'MyUserModelClassName')), --> if user is always a single user object
     * <li>array('user'=>array(self::IS_MANY,'MyUserModelClassName')) --> if user contains an ARRAY of users
     * </ul>
     * This will cause the class to automatically load the User object/objects. It enables you to use magic getters like: $tweet->user->name where tweet is your main model object and user is a ActiveResource contained within a tweet response.
     * @return array The configuration of this classed as used by EActiveResourceMetaData.
     */
    public function rest()
    {
        return array(
            'site'=>'http://localhost:port',
            'resource'=>'resourcename',
            'idProperty'=>'id',
            'contenttype'=>'application/json',
            'accepttype'=>'application/json',
        );
    }

    /**
     * Returns the content type as specified within Configuration()
     * @return string
     */
    public function getContentType()
    {
        return $this->getMetaData()->contenttype;
    }

    /**
     * Returns the accept type as specified within Configuration()
     * @return string
     */
    public function getAcceptType()
    {
        return $this->getMetaData()->accepttype;
    }

    /**
     * Returns the site as specified within Configuration()
     * @return string
     */
    public function getSite()
    {
        return $this->getMetaData()->site;
    }

    /**
     * Returns the resource as specified within Configuration()
     * @return string
     */
    public function getResource()
    {
        return $this->getMetaData()->resource;
    }

    /**
     * Returns the file extension as specified within Configuration()
     * @return string
     */
    public function getFileExtension()
    {
        return $this->getMetaData()->fileextension;
    }

    /**
     * Returns the container field as specified within Configuration()
     * @return string
     */
    public function getContainer()
    {
        return $this->getMetaData()->container;
    }

    /**
     * Returns the embedded fields as specified within Configuration()
     * @return string
     */
    public function getEmbedded()
    {
        return $this->getMetaData()->embedded;
    }

    /**
     * Returns the idProperty as specified within Configuration()
     * @return string
     */
    public function idProperty()
    {
        return $this->getMetaData()->idProperty;
    }

    /**
     * Returns the id of this ActiveResource model. You need an id in order to send update requests.
     * @return string
     */
    public function getId()
    {
        $id=$this->idProperty();
        if($id!=null)
            return $this->$id;
        else
            throw new EActiveResourceException('No id property defined. You have to define one in your resource configuration!');
    }

    /**
     * Returns the list of all attribute names of the model.
     * @return array list of attribute names.
     */
    public function attributeNames()
    {
        return array_keys($this->getMetaData()->properties);
    }

    /**
     * Initializes this model.
     * This method is invoked when an instance is newly created and has
     * its {@link scenario} set.
     * You may override this method to provide code that is needed to initialize the model (e.g. setting
     * initial property values.)
     */
    public function init()
    {
    }

    /**
     * PHP sleep magic method.
     */
    public function __sleep()
    {
        return array_keys((array)$this);
    }

    /**
     * PHP getter magic method.
     * This method is overridden so that node/relationship properties can be accessed.
     * @param string $name property name
     * @return mixed property value
     * @see getAttribute
     */
    public function __get($name)
    {
            if(isset($this->_attributes[$name]))
                    return $this->_attributes[$name];
            else if(isset($this->getMetaData()->properties[$name]))
                    return null;
            else if(isset($this->_embedded[$name]))
                    return $this->_embedded[$name];
            else if(!$this->getMetaData()->schema)
                    return null;
            else
                    return parent::__get($name);
    }

    /**
     * PHP setter magic method.
     * This method is overridden so that AR attributes can be accessed like properties.
     * @param string $name property name
     * @param mixed $value property value
     */
    public function __set($name,$value)
    {
            if($this->setAttribute($name,$value)===false)
            {
                    parent::__set($name,$value);
            }
    }

    /**
     * Checks if a property value is null.
     * This method overrides the parent implementation by checking
     * if the named attribute is null or not.
     * @param string $name the property name or the event name
     * @return boolean whether the property value is null
     */
    public function __isset($name)
    {
            if(isset($this->_attributes[$name]))
                    return true;
            else if(isset($this->getMetaData()->properties[$name]))
                    return false;
            else
                    return parent::__isset($name);
    }

    /**
     * Sets a component property to be null.
     * This method overrides the parent implementation by clearing
     * the specified attribute value.
     * @param string $name the property name or the event name
     */
    public function __unset($name)
    {
            if(isset($this->getMetaData()->properties[$name]))
                    unset($this->_attributes[$name]);
            else
                    parent::__unset($name);
    }

    /**
     * Returns the static model of the specified EAR class.
     * The model returned is a static instance of the EAR class.
     * It is provided for invoking class-level methods (something similar to static class methods.)
     *
     * EVERY derived ActiveResource class must override this method as follows,
    * <pre>
     * public static function model($className=__CLASS__)
     * {
     *     return parent::model($className);
     * }
     * </pre>
     *
     * @param string $className active resource class name.
     * @return EAR active resource model instance.
     */
    public static function model($className=__CLASS__)
    {
            if(isset(self::$_models[$className]))
                    return self::$_models[$className];
            else
            {
                    $model=self::$_models[$className]=new $className(null);
                    $model->_md=new EActiveResourceMetaData($model);
                    $model->attachBehaviors($model->behaviors());
                    return $model;
            }
    }

    /**
     * Checks whether this ActiveResource has the named attribute
     * @param string $name attribute name
     * @return boolean whether this ActiveResource has the named attribute.
     */
    public function hasAttribute($name)
    {
            return isset($this->getMetaData()->properties[$name]);
    }

    /**
     * Overrides the CModel method in order to provide schemaless assignments.
     * @param array $values
     * @param boolean $safeOnly
     */
    public function setAttributes($values,$safeOnly=true)
    {
        if(!is_array($values))
            return;
        if(!$this->getMetaData()->schema) //schemaless variant
        {
            if($safeOnly)
            {
                $attributes=array_flip($this->getSafeAttributeNames());
                foreach($attributes as $name=>$value)
                    if(isset($values[$name])) $this->setAttribute($name,$values[$name]);
            }
            else
                foreach($values as $name=>$value)
                    $this->setAttribute($name,$value);
        }
        else //classic variant like in CModel
        {
            $attributes=array_flip($safeOnly ? $this->getSafeAttributeNames() : $this->attributeNames());
            foreach($values as $name=>$value)
            {
                if(isset($attributes[$name]))
                    $this->$name=$value;
                else if($safeOnly)
                    $this->onUnsafeAttribute($name,$value);
            }
        }
    }

    /**
     * Returns the named attribute value.
     * If this is a new resource and the attribute is not set before,
     * the default value will be returned.
     * You may also use $this->AttributeName to obtain the attribute value.
     * @param string $name the attribute name
     * @return mixed the attribute value. Null if the attribute is not set or does not exist.
     * @see hasAttribute
     */
    public function getAttribute($name)
    {
            if(property_exists($this,$name))
                    return $this->$name;
            else if(isset($this->_attributes[$name]))
                    return $this->_attributes[$name];
    }

    /**
     * Sets the named attribute value.
     * You may also use $this->AttributeName to set the attribute value.
     * @param string $name the attribute name
     * @param mixed $value the attribute value.
     * @return boolean whether the attribute exists and the assignment is conducted successfully
     * @see hasAttribute
     */
    public function setAttribute($name,$value)
    {
            if(property_exists($this,$name))
                    $this->$name=$value;
            else if(isset($this->getMetaData()->properties[$name]) || !$this->getMetaData()->schema)
                    $this->_attributes[$name]=$value;
            else
                    return false;
            return true;
    }

    /**
     * Returns all attribute values.
     * @param mixed $names names of attributes whose value needs to be returned.
     * If this is true (default), then all attribute values will be returned
     * If this is null, all attributes will be returned.
     * @return array attribute values indexed by attribute names.
     */
    public function getAttributes($names=true)
    {
            $attributes=$this->_attributes;
            
            foreach($this->getMetaData()->properties as $name=>$type)
            {
                    if(property_exists($this,$name))
                            $attributes[$name]=$this->$name;
                    else if($names===true && !isset($attributes[$name]))
                            $attributes[$name]=null;
            }
            if(is_array($names))
            {
                    $attrs=array();
                    foreach($names as $name)
                    {
                            if(property_exists($this,$name))
                                    $attrs[$name]=$this->$name;
                            else
                                    $attrs[$name]=isset($attributes[$name])?$attributes[$name]:null;
                    }
                    return $attrs;
            }
            else
                return $attributes;
            
    }

    /**
     * Saves the current resource.
     *
     * A post request to the resource will be send if its {@link isNewresource}
     * property is true (usually the case when the resource is created using the 'new'
     * operator). Otherwise, it will be used to update the resource
     * (usually the case if the resource is obtained using one of those 'find' methods.)
     *
     * Validation will be performed before saving the resource. If the validation fails,
     * the resource will not be saved. You can call {@link getErrors()} to retrieve the
     * validation errors.
     *
     * If the resource is saved via insertion, its {@link isNewRecord} property will be
     * set false, and its {@link scenario} property will be set to be 'update'.
     *
     * @param boolean $runValidation whether to perform validation before saving the resource.
     * If the validation fails, the resource will not be saved to database.
     * @param array $attributes list of attributes that need to be saved. Defaults to null,
     * meaning all attributes that are loaded from the service will be saved.
     * @return boolean whether the saving succeeds
     */
    public function save($runValidation=true,$attributes=null)
    {
            if(!$runValidation || $this->validate($attributes))
                    return $this->getIsNewResource() ? $this->create($attributes) : $this->update($attributes);
            else
                    return false;
    }

    /**
     * Returns if the current resource is new.
     * @return boolean whether the resource is new and should be created when calling {@link save}.
     * This property is automatically set in constructor and {@link populateRecord}.
     * Defaults to false, but it will be set to true if the instance is created using
     * the new operator.
     */
    public function getIsNewResource()
    {
            return $this->_new;
    }

    /**
     * Sets if the resource is new.
     * @param boolean $value whether the resource is new and should be created when calling {@link save}.
     * @see getIsNewResource
     */
    public function setIsNewResource($value)
    {
            $this->_new=$value;
    }

    /**
     * This event is raised before the resource is saved.
     * By setting {@link CModelEvent::isValid} to be false, the normal {@link save()} process will be stopped.
     * @param CModelEvent $event the event parameter
     * @since 1.0.2
     */
    public function onBeforeSave($event)
    {
            $this->raiseEvent('onBeforeSave',$event);
    }

    /**
     * This event is raised after the resource is saved.
     * @param CEvent $event the event parameter
     * @since 1.0.2
     */
    public function onAfterSave($event)
    {
            $this->raiseEvent('onAfterSave',$event);
    }

    /**
     * This event is raised before the resource is deleted.
     * By setting {@link CModelEvent::isValid} to be false, the normal {@link delete()} process will be stopped.
     * @param CModelEvent $event the event parameter
     * @since 1.0.2
     */
    public function onBeforeDelete($event)
    {
            $this->raiseEvent('onBeforeDelete',$event);
    }

    /**
     * This event is raised after the resource is deleted.
     * @param CEvent $event the event parameter
     * @since 1.0.2
     */
    public function onAfterDelete($event)
    {
            $this->raiseEvent('onAfterDelete',$event);
    }

    /**
     * This event is raised before an AR finder performs a find call.
     * In this event, the {@link CModelEvent::criteria} property contains the query criteria
     * passed as parameters to those find methods. If you want to access
     * the query criteria specified in scopes, please use {@link getDbCriteria()}.
     * You can modify either criteria to customize them based on needs.
     * @param CModelEvent $event the event parameter
     * @see beforeFind
     * @since 1.0.9
     */
    public function onBeforeFind($event)
    {
            $this->raiseEvent('onBeforeFind',$event);
    }

    /**
     * This event is raised after the resource is instantiated by a find method.
     * @param CEvent $event the event parameter
     * @since 1.0.2
     */
    public function onAfterFind($event)
    {
            $this->raiseEvent('onAfterFind',$event);
    }

    /**
     * This method is invoked before saving a resource (after validation, if any).
     * The default implementation raises the {@link onBeforeSave} event.
     * You may override this method to do any preparation work for resource saving.
     * Use {@link isNewRecord} to determine whether the saving is
     * for inserting or updating resource.
     * Make sure you call the parent implementation so that the event is raised properly.
     * @return boolean whether the saving should be executed. Defaults to true.
     */
    protected function beforeSave()
    {
            if($this->hasEventHandler('onBeforeSave'))
            {
                    $event=new CModelEvent($this);
                    $this->onBeforeSave($event);
                    return $event->isValid;
            }
            else
                    return true;
    }

    /**
     * This method is invoked after saving a resource successfully.
     * The default implementation raises the {@link onAfterSave} event.
     * You may override this method to do postprocessing after resource saving.
     * Make sure you call the parent implementation so that the event is raised properly.
     */
    protected function afterSave()
    {
            if($this->hasEventHandler('onAfterSave'))
                    $this->onAfterSave(new CEvent($this));
    }

    /**
     * This method is invoked before deleting a resource.
     * The default implementation raises the {@link onBeforeDelete} event.
     * You may override this method to do any preparation work for resource deletion.
     * Make sure you call the parent implementation so that the event is raised properly.
     * @return boolean whether the resource should be deleted. Defaults to true.
     */
    protected function beforeDelete()
    {
            if($this->hasEventHandler('onBeforeDelete'))
            {
                    $event=new CModelEvent($this);
                    $this->onBeforeDelete($event);
                    return $event->isValid;
            }
            else
                    return true;
    }

    /**
     * This method is invoked after deleting a resource.
     * The default implementation raises the {@link onAfterDelete} event.
     * You may override this method to do postprocessing after the resource is deleted.
     * Make sure you call the parent implementation so that the event is raised properly.
     */
    protected function afterDelete()
    {
            if($this->hasEventHandler('onAfterDelete'))
                    $this->onAfterDelete(new CEvent($this));
    }

    /**
     * This method is invoked before an AR finder executes a find call.
     * The find calls include {@link find}, {@link findAll}, {@link findByPk},
     * {@link findAllByPk}, {@link findByAttributes} and {@link findAllByAttributes}.
     * The default implementation raises the {@link onBeforeFind} event.
     * If you override this method, make sure you call the parent implementation
     * so that the event is raised properly.
     *
     * Starting from version 1.1.5, this method may be called with a hidden {@link CDbCriteria}
     * parameter which represents the current query criteria as passed to a find method of AR.
     *
     * @since 1.0.9
     */
    protected function beforeFind()
    {
            if($this->hasEventHandler('onBeforeFind'))
            {
                    $event=new CModelEvent($this);
                    // for backward compatibility
                    $event->criteria=func_num_args()>0 ? func_get_arg(0) : null;
                    $this->onBeforeFind($event);
            }
    }

    /**
     * This method is invoked after each resource is instantiated by a find method.
     * The default implementation raises the {@link onAfterFind} event.
     * You may override this method to do postprocessing after each newly found resource is instantiated.
     * Make sure you call the parent implementation so that the event is raised properly.
     */
    protected function afterFind()
    {
            if($this->hasEventHandler('onAfterFind'))
                    $this->onAfterFind(new CEvent($this));
    }

    /**
     * Calls {@link beforeFind}.
     * This method is internally used.
     * @since 1.0.11
     */
    public function beforeFindInternal()
    {
            $this->beforeFind();
    }

    /**
     * Calls {@link afterFind}.
     * This method is internally used.
     * @since 1.0.3
     */
    public function afterFindInternal()
    {
            $this->afterFind();
    }

    /**
     * This method is used internally by the finder methods to instantiate models.
     * @param array $attributes The attributes the model has to be instantiated with
     * @return EActiveResource The instantiated model
     */
    protected function instantiate($attributes)
    {
        $class=get_class($this);
        $model=new $class(null);
        return $model;
    }

    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }
    
    /**
     * "Inserts" a new resource in the collection.
     * The id will be populated with the actual value after insertion.
     * Note, validation is not performed in this method. You may call {@link validate} to perform the validation.
     * After the resource is inserted to the service successfully, its {@link isNewRecord} property will be set false,
     * and its {@link scenario} property will be set to be 'update'.
     * @param array $properties list of attributes that need to be saved. Defaults to null,
     * meaning all attributes that are loaded from the service will be saved.
     * @return boolean whether the attributes are valid and the resource is inserted successfully.
     * @throws EActiveResourceException if the resource is not new
     */
     public function create($attributes=null)
     {
        if(!$this->getIsNewResource())
            throw new EActiveResourceException('The resource cannot be inserted because it is not new.');
        if($this->beforeSave())
        {
            Yii::trace(get_class($this).'.create()','ext.EActiveResource');

            $response=$this->postRequest(null,$this->getAttributes());
            $returnedmodel=$this->populateRecord($response);

            if($returnedmodel)
            {
                $id=$this->idProperty();
                $this->$id=$returnedmodel->getId();
            }
            
            $this->afterSave();
            $this->setIsNewResource(false);
            $this->setScenario('update');
            return true;

        }
        return false;
     }

     /**
     * Updates the row represented by this active resource.
     * All loaded attributes will be saved to the service.
     * Note, validation is not performed in this method. You may call {@link validate} to perform the validation.
     * @param array $attributes list of attributes that need to be saved. Defaults to null,
     * meaning all attributes that are loaded from the service will be saved.
     * @return boolean whether the update is successful
     * @throws EActiveResourceException if the resource is new
     */
    public function update($attributes=null)
    {

            if($this->getIsNewResource())
                    throw new EActiveResourceException(Yii::t('ext.EActiveResource','The resource cannot be updated because it is new.'));
            if($this->beforeSave())
            {
                    Yii::trace(get_class($this).'.update()','ext.EActiveResource');
                    $this->updateById($this->getId(),$this->getAttributes($attributes));
                    $this->afterSave();
                    return true;
            }
            else
                    return false;
    }

    /**
     * Saves a selected list of attributes.
     * Unlike {@link save}, this method only saves the specified attributes
     * of an existing row dataset and does NOT call either {@link beforeSave} or {@link afterSave}.
     * Also note that this method does neither attribute filtering nor validation.
     * So do not use this method with untrusted data (such as user posted data).
     * You may consider the following alternative if you want to do so:
     * <pre>
     * $postRecord=Post::model()->findById($postID);
     * $postRecord->attributes=$_POST['post'];
     * $postRecord->save();
     * </pre>
     * @param array $attributes attributes to be updated. Each element represents an attribute name
     * or an attribute value indexed by its name. If the latter, the resource's
     * attribute will be changed accordingly before saving.
     * @return boolean whether the update is successful
     * @throws EActiveResourceException if the resource is new
     */
    public function saveAttributes($attributes)
    {
            if(!$this->getIsNewResource())
            {
                    Yii::trace(get_class($this).'.saveAttributes()','ext.EActiveResource');
                    $values=array();
                    foreach($attributes as $name=>$value)
                    {
                            if(is_integer($name))
                                    $values[$value]=$this->$value;
                            else
                                    $values[$name]=$this->$name=$value;
                    }

                    if($this->updateById($this->getId(),$values)>0)
                    {
                            return true;
                    }
                    else
                            return false;
            }
            else
                    throw new EActiveResourceException(Yii::t('ext.EActiveResource','The resource cannot be updated because it is new.'));
    }

    /**
     * Deletes the resource
     * @return boolean whether the deletion is successful.
     * @throws EActiveResourceException if the resource is new
     */
    public function destroy()
    {
            if(!$this->getIsNewResource())
            {
                    Yii::trace(get_class($this).'.destroy()','ext.EActiveResource');
                    if($this->beforeDelete())
                    {
                            $result=$this->deleteById($this->getId())>0;
                            $this->afterDelete();
                            return $result;
                    }
                    else
                            return false;
            }
            else
                    throw new EActiveResourceException(Yii::t('ext.EActiveResource','The resource cannot be deleted because it is new.'));
    }

    /**
     * Repopulates this resource with the latest data.
     * @return boolean whether the row still exists in the database. If true, the latest data will be populated to this active resource.
     */
    public function refresh()
    {
            Yii::trace(get_class($this).'.refresh()','ext.EActiveResource');
            if(!$this->getIsNewRecord() && ($resource=$this->findById($this->getId()))!==null)
            {
                    $this->_attributes=array();
                    foreach($this->getMetaData()->properties as $name=>$value)
                    {
                            if(property_exists($this,$name))
                                    $this->$name=$resource->$name;
                            else
                                    $this->_attributes[$name]=$resource->$name;
                    }
                    return true;
            }
            else
                    return false;
    }

    /**
     * Compares current active resource with another one.
     * The comparison is made by comparing collection name, site and id values of the two active resources.
     * @param EActiveResource $resource resource to compare to
     * @return boolean whether the two active resources refer to the same service entry.
     */
    public function equals($resource)
    {
            return $this->getSite()===$resource->getSite() && $this->getResource()===$resource->getResource() && $this->getId()===$resource->getId();
    }

    /**
     * Finds a single active resource with the specified id.
     * @param mixed $id The id.
     * @return EActiveResource the resource found. Null if none is found.
     */
    public function findById($id)
    {
            Yii::trace(get_class($this).'.findById()','ext.EActiveResource');
            $response=$this->getRequest($id);
            return $this->populateRecord($response->getData());
    }

    /**
     * Updates resources with the specified id
     * Note, the attributes are not checked for safety and validation is NOT performed.
     * @param mixed $id the id of the resource
     * @param array $attributes list of attributes (name=>$value) to be updated
     */
    public function updateById($id,$attributes)
    {
            Yii::trace(get_class($this).'.updateById()','ext.EActiveResource');
            $response=$this->putRequest($id,$attributes);
    }

    /**
     * Deletes rows with the specified id.
     * @param integer $id primary key value(s).
     */
    public function deleteById($id)
    {
            Yii::trace(get_class($this).'.deleteById()','ext.EActiveResource');
            $response=$this->deleteRequest($id);
    }

    /**
     * Creates an active resource with the given attributes.
     * This method is internally used by the find methods.
     * @param array $attributes attribute values (column name=>column value)
     * @param boolean $callAfterFind whether to call {@link afterFind} after the resource is populated.
     * This parameter is added in version 1.0.3.
     * @return EActiveResource the newly created active resource. The class of the object is the same as the model class.
     * Null is returned if the input data is false.
     */
    public function populateRecord($attributes,$callAfterFind=true)
    {
        if(is_array($attributes) && array_key_exists($this->getContainer(),$attributes))
        {
                $attributes=$this->extractDataFromResponse($attributes);
                Yii::trace('Container field found: '.$this->getContainer().'. Repopulating!','ext.EActiveResource');
        }

        //if(isset($attributes[$this->getContainer()]))
        //{
        //            Yii::trace('Container field found: '.$this->getContainer().'. Repopulating!','ext.EActiveResource');
        //            //this array position is the actual object so try again
        //            return $this->populateRecord($attributes[$this->getContainer()]);
        //}
        if ($attributes!==false && is_array($attributes))
        {
                $resource=$this->instantiate($attributes);
                $resource->setScenario('update');
                $resource->init();
                foreach($attributes as $name=>$value)
                {
                        if(property_exists($resource,$name))
                                $resource->$name=$value;
                        //CHECK IF THERE IS SCHEMA
                        else if(!$this->getMetaData()->schema)
                        {
                            //THERE IS NO SCHEMA, SO CHECK FOR EMBEDDED MODELS FIRST
                            if(isset($this->getMetaData()->embedded[$name]))
                            {
                                    if($this->getMetaData()->embedded[$name][0]==self::IS_ONE)
                                    {
                                        Yii::trace('Populating instance of ' .get_class($this). ': Position ['.$name.'] contains an object of class ' .$this->getMetaData()->embedded[$name][1],'ext.EActiveResource');
                                        $class=$this->getMetaData()->embedded[$name][1];
                                        $object=self::model($class);
                                        $resource->_embedded[$name]=$object->populateRecord($value);
                                    }
                                    else if($this->getMetaData()->embedded[$name][0]==self::IS_MANY)
                                    {
                                        Yii::trace('Populating instance of ' .get_class($this). ': Position ['.$name.'] contains multiple objects of class ' .$this->getMetaData()->embedded[$name][1],'ext.EActiveResource');
                                        $class=$this->getMetaData()->embedded[$name][1];
                                        $object=self::model($class);
                                        $resource->_embedded[$name]=$object->populateRecords($value);
                                    }
                            }
                            else //IF ALL EMBEDDED MODLES ARE CHECKED, ASSIGN THE REST OF THE VALUES TO THE ATTRIBUTES ARRAY BECAUSE WE ARE COMPLETELY SCHEMALESS
                                $resource->_attributes[$name]=$value;                                    
                        }
                        else
                        //////WE HAVE A SCHEMA DEFINED SO ASSIGN THE ATTRIBUTES ACCORDING TO THE DEFINED PROPERTIES
                        {
                            if((isset($this->getMetaData()->properties[$name])))
                                $resource->_attributes[$name]=$value;
                            else if(isset($this->getMetaData()->embedded[$name]))
                            {       if($this->getMetaData()->embedded[$name][0]==self::IS_ONE)
                                    {
                                        Yii::trace('Populating instance of ' .get_class($this). ': Position ['.$name.'] contains an object of class ' .$this->getMetaData()->embedded[$name][1],'ext.EActiveResource');
                                        $class=$this->getMetaData()->embedded[$name][1];
                                        $object=self::model($class);
                                        $resource->_embedded[$name]=$object->populateRecord($value);
                                    }
                                    else if($this->getMetaData()->embedded[$name][0]==self::IS_MANY)
                                    {
                                        Yii::trace('Populating instance of ' .get_class($this). ': Position ['.$name.'] contains multiple objects of class ' .$this->getMetaData()->embedded[$name][1],'ext.EActiveResource');
                                        $class=$this->getMetaData()->embedded[$name][1];
                                        $object=self::model($class);
                                        $resource->_embedded[$name]=$object->populateRecords($value);
                                    }
                            }
                        }

                }
                $resource->attachBehaviors($resource->behaviors());
                if($callAfterFind)
                        $resource->afterFind();
                return $resource;
            }
            else
                    return null;
    }

    /**
     * Creates a list of active resources based on the input data.
     * This method is internally used by the find methods.
     * @param array $data list of attribute values for the active resources.
     * @param boolean $callAfterFind whether to call {@link afterFind} after each resource is populated.
     * @param string $index the name of the attribute whose value will be used as indexes of the query result array.
     * If null, it means the array will be indexed by zero-based integers.
     * @return array list of active resources.
     */
    public function populateRecords($data,$callAfterFind=true,$index=null)
    {
            $resources=array();

            if($this->getContainer())
                    $data=$this->extractDataFromResponse($data);

            foreach($data as $attributes)
            {
                    if(($resource=$this->populateRecord($attributes,$callAfterFind))!==null)
                    {
                            if($index===null)
                                    $resources[]=$resource;
                            else
                                    $resources[$resource->$index]=$resource;
                    }
            }

            return $resources;
    }

    /**
     * This method tries to extract a subarray within an response that contains a field that is recognized as container field (as specified within Configuration())
     * @param array $array The array containing the data
     * @return array The array only containing the relevant fields.
     */
    public function extractDataFromResponse($array)
    {
            if (is_array($array))
            {
                if (isset($array[$this->getContainer()]))
                        return $array[$this->getContainer()];
                foreach ($array as $item)
                {
                    $return = $this->extractDataFromResponse($item);
                    if (!is_null($return))
                        return $return;
                }
            }
            else
                return $array;
    }

    /**
     * Send a GET request to this resource.
     * @param string $id The id of the resource. This is optional. Set to null if you want to send a request like GET 'http://iamaRESTapi/apiversion/people'
     * @param string $additional Some requests need some additional uri extensions like getting all people that were fired. GET 'http://iamaRESTapi/apiversion/people/fired'. Set to '/fired' if you want to send a request like that
     * @param array $customHeader A custom header
     * @return EActiveResourceResponse The response object
     */
    public function getRequest($id=null,$additional=null,$customHeader=array())
    {
        $uri='';
        if($this->getSite())
            $uri.=$this->getSite();
        if($this->getResource())
            $uri.='/'.$this->getResource();
        if($id)
            $uri.='/'.$id;
        if($additional)
            $uri.=$additional;
        if($this->getFileExtension())
            $uri.=$this->getFileExtension();
        
        return $this->sendRequest($uri, EActiveResourceRequest::METHOD_GET,$customHeader);
    }

    /**
     * Send a custom GET request if the standard version isn't doing it. But you have to define the whole uri by yourself
     * @param string $uri The whole uri
     * @return EActiveResourceResponse The response object
     */
    public function customGetRequest($uri,$customHeader=array())
    {
        return $this->sendRequest($uri,EActiveResourceRequest::METHOD_GET,$customHeader);
    }

    /**
     * Send a PUT request to this resource.
     * @param string $id The id of the resource. This is optional. Set to null if you want to send a request like PUT 'http://iamaRESTapi/apiversion/people'
     * @param array $data An array containing the data to be sent to the service. Defaults to the attributes of this model as an associative array.
     * @param string $additional Some requests need some additional uri extensions like modifying all people that were fired. PUT 'http://iamaRESTapi/apiversion/people/fired'. Set to '/fired' if you want to send a request like that
     * @param array $customHeader A custom header
     * @return EActiveResourceResponse The response object
     */
    public function putRequest($id=null,$data=null,$additional=null,$customHeader=array())
    {
        $uri='';
        if($this->getSite())
            $uri.=$this->getSite();
        if($this->getResource())
            $uri.='/'.$this->getResource();
        if($id)
            $uri.='/'.$id;
        if($additional)
            $uri.=$additional;
        return $this->sendRequest($uri,EActiveResourceRequest::METHOD_PUT,$customHeader,$data);
    }

    /**
     * Send a custom PUT request if the standard version isn't doing it. But you have to define the whole uri by yourself
     * @param string $uri The whole uri
     * @params array $data The data to be sent
     * @param array $customHeader A custom header
     * @return EActiveResourceResponse The response object
     */
    public function customPutRequest($uri,$data,$customHeader=array())
    {
        return $this->sendRequest($uri,EActiveResourceRequest::METHOD_PUT,$customHeader,$data);
    }

    /**
     * Send a POST request to this resource.
     * @param string $id The id of the resource. This is optional. Set to null if you want to send a request like POST 'http://iamaRESTapi/apiversion/people'
     * @param array $data An array containing the data to be sent to the service. Defaults to the attributes of this model as an associative array.
     * @param string $additional Some requests need some additional uri extensions like modifying all people that were fired. POST 'http://iamaRESTapi/apiversion/people/fired'. Set to '/fired' if you want to send a request like that
     * @param array $customHeader A custom header
     * @return EActiveResourceResponse The response object
     */
    public function postRequest($id=null,$data=null,$additional=null,$customHeader=array())
    {
        $uri='';
        if($this->getSite())
            $uri.=$this->getSite();
        if($this->getResource())
            $uri.='/'.$this->getResource();
        if($id)
            $uri.='/'.$id;
        if($additional)
            $uri.=$additional;
        return $this->sendRequest($uri,EActiveResourceRequest::METHOD_POST,$customHeader,$data);
    }

    /**
     * Send a custom POST request if the standard version isn't doing it. But you have to define the whole uri by yourself
     * @param string $uri The whole uri
     * @param array $data The data to be sent
     * @param array $customHeader A custom header
     * @return EActiveResourceResponse The response object
     */
    public function customPostRequest($uri,$data,$customHeader=array())
    {
        return $this->sendRequest($uri,EActiveResourceRequest::METHOD_POST,$customHeader,$data);
    }

    public function deleteRequest($id=null,$additional=null,$customHeader=array())
    {
        $uri='';
        if($this->getSite())
            $uri.=$this->getSite();
        if($this->getResource())
            $uri.='/'.$this->getResource();
        if($id)
            $uri.='/'.$id;
        if($additional)
            $uri.=$additional;
        return $this->sendRequest($uri,EActiveResourceRequest::METHOD_DELETE,$customHeader);
    }

    /**
     * Send a custom DELETE request if the standard version isn't doing it. But you have to define the whole uri by yourself
     * @param string $uri The whole uri
     * @param array $customHeader A custom header
     * @return EActiveResourceResponse The response object
     */
    public function customDeleteRequest($uri,$customHeader=array())
    {
        return $this->sendRequest($uri,EActiveResourceRequest::METHOD_DELETE,$customHeader);
    }

    /**
     * Creates a new request, sends and receives the data.
     * @param string $uri The uri this request is sent to.
     * @param string $method The method (GET,PUT,POST,DELETE)
     * @param array $customHeader A customHeader to be sent
     * @param array $data The data to be sent as array
     * @return EActiveResourceResponse The response object
     */
    protected function sendRequest($uri,$method,$customHeader,$data=null)
    {
        $request=new EActiveResourceRequest;

        $request->setUri($uri);
        $request->setMethod($method);
        $request->setData($data);
        $request->setContentType($this->getContentType());
        $request->setAcceptType($this->getAcceptType());
        $request->setCustomHeader($customHeader);

        $response=$request->run();
                
        return $response;

    }
}

?>
