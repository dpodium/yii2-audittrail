# Yii2-audittrail
Yii2-audittrail tracks changes to a model with behavior implementation.

Based on package [asinfotrack/yii2-audittrail](https://github.com/asinfotrack/yii2-audittrail) by Pascal Mueller, AS infotrack AG.

## Installation
The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```bash
$ composer require dpodium/yii2-audittrail
```

or add

```
"dpodium/yii2-audittrail": "dev-master"
```

to the `require` section of your `composer.json` file.


## Migration
	
After downloading everything you need to apply the migration creating the audit trail entry table:

	yii migrate --migrationPath=@vendor/dpodium/yii2-audittrail/migrations
	
To remove the table just do the same migration downwards.

## Usage

#### Behavior
Attach the behavior to your model and you're done:

```php
public function behaviors()
{
    return [
    	// ...
    	'yii2-audittrail'=>[
    		'class'=>AuditTrailBehavior::className(),
    		
    		// some of the optional configurations
    		'ignoredAttributes'=>['created_at','updated_at'],
    		'consoleUserId'=>1, 
			'attributeOutput'=>[
				'desktop_id'=>function ($value) {
					$model = Desktop::findOne($value);
					return sprintf('%s %s', $model->manufacturer, $model->device_name);
				},
				'last_checked'=>'datetime',
			],
    	],
    	// ...
    ];
}
```

### Widget
The widget is also very easy to use. Just provide the model to get the audit trail for:

```php
<?= AuditTrail::widget([
	'model'=>$model,
	
	// some of the optional configurations
	'userIdCallback'=>function ($userId, $model) {
 		return User::findOne($userId)->fullname;
	},
	'changeTypeCallback'=>function ($type, $model) {
		return Html::tag('span', strtoupper($type), ['class'=>'label label-info']);
	},
	'dataTableOptions'=>['class'=>'table table-condensed table-bordered'],
]) ?>
```
