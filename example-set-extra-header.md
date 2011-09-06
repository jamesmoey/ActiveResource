#Setting extra header

[php]
<php
class Person extends EActiveResource
{

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function rest()
    {
	$this->embedToken();

        return array(
            'site'=>'http://api.aRESTservice.com',
            'resource'=>'people',
            'contenttype'=>'application/json',
            'accepttype'=>'application/json',
            'fileextension'=>'.json',
        );
    }

	public function embedToken() {
		$token = user()->getState('token');
		if (!empty($token)) {
			$this->setExtraHeader(array('TOKEN: ' . $token));
		}
	}   
}
?>
[/php]
