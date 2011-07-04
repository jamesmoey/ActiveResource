<?php
/**
 * @author Johannes "Haensel" Bauer
 * @version 0.1
 */

/**
 * This is the ActiveResource version of the CActiveMetaData class. It is used by ActiveResources to define
 * vital parameters for a RESTful communication between Yii and the service.
 */
class EActiveResourceMetaData
{

    public $properties;     //The properties of the resource according to the schema configuration
    public $attributeDefaults=array();

    public $schema; 
    public $site;
    public $resource;
    public $container;
    public $embedded;
    public $fileextension;
    public $idProperty;
    public $contenttype;
    public $accepttype;


    private $_model;

    public function __construct($model)
    {
        $this->_model=$model;

        $this->schema=null;
        foreach($model->Configuration() as $option=>$value)
                if(property_exists($this, $option))
                        $this->$option=$value;

        $this->properties=$this->getProperties();

    }

    /**
     * Define the attributes of the model. These are set to all public properties by default.
     * Override this method if you want to allow specific properties only.
     * @return an array of properties
     */
    protected function getProperties()
    {
        $names=array();
        //add dynamic attributes if this is a schemaless resource
        if($this->schema==null)
            foreach($this->_model->getAttributesArray() as $attribute=>$value)
                $names[$attribute]=$value;
        else
            foreach($this->schema as $property=>$params)
                if($params[0]=='IS_PROPERTY')
                    $names[$property]=$params[1];

        return $this->properties=$names;
    }

}

?>
